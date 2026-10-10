<?php
namespace Tests\Integration;

/** Module regression on the same dedicated QA instance. */
class AgendaUserMariaDbTest extends \Tests\Feature\Security\UserEditorSessionTest
{
    public function createApplication() { return \Tests\Support\IsolatedAgendaQa::boot(); }
}
