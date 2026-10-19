<?php
namespace App\Services\Scheduling;

use App\Models\Doctor;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;

/** Per-user nonclinical preferences, shared between devices. No patient identity. */
final class AgendaProfessionalPreferences
{
    private function authorize(User $actor): void
    {
        abort_unless($actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW),403);
    }
    public function read(User $actor): array
    {
        $this->authorize($actor);
        $rows=DB::table('agenda_professional_preferences as p')->join('doctors as d','d.id','=','p.doctor_id')
            ->where('p.user_id',$actor->id)->where('d.estado','ACTIVO')->get(['p.doctor_id','p.favorite','p.selection_count','p.last_selected_at']);
        return ['favorites'=>$rows->where('favorite',true)->pluck('doctor_id')->map(fn($id)=>(int)$id)->values()->all(),
            'frequent'=>$rows->where('selection_count','>',0)->sortByDesc('selection_count')->take(5)->pluck('doctor_id')->map(fn($id)=>(int)$id)->values()->all(),
            'last'=>$rows->whereNotNull('last_selected_at')->sortByDesc('last_selected_at')->first()?->doctor_id];
    }
    public function change(User $actor, int $doctorId, string $action): array
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor,$doctorId,$action) {
            // Serialize this user's preferences only, including simultaneous favorite limit checks.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless(Doctor::whereKey($doctorId)->where('estado','ACTIVO')->exists(),422,'El profesional ya no está activo.');
            abort_unless(in_array($action,['select','favorite','unfavorite'],true),422);
            $row=DB::table('agenda_professional_preferences')->where('user_id',$actor->id)->where('doctor_id',$doctorId)->first();
            if($action==='favorite' && !$row?->favorite) {
                abort_if(DB::table('agenda_professional_preferences')->where('user_id',$actor->id)->where('favorite',true)->count()>=5,422,'Puedes guardar hasta cinco favoritos.');
            }
            $changes=['updated_at'=>now()];
            if($action==='select') $changes+=['selection_count'=>min(4294967295,($row?->selection_count??0)+1),'last_selected_at'=>now()->format('Y-m-d H:i:s.u')];
            else $changes['favorite']=$action==='favorite';
            if($row) DB::table('agenda_professional_preferences')->where('id',$row->id)->update($changes);
            else DB::table('agenda_professional_preferences')->insert($changes+['user_id'=>$actor->id,'doctor_id'=>$doctorId,'created_at'=>now()]);
        },3);
        return $this->read($actor);
    }
}
