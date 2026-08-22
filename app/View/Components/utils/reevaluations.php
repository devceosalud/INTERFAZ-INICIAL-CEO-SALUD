<?php

namespace App\View\Components\utils;

use Illuminate\View\Component;

class reevaluations extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $reevaluaciones;

    public function __construct($reevaluaciones)
    {
        //
        $this->reevaluaciones = $reevaluaciones;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.utils.reevaluations');
    }
}
