<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Schemas\TripForm;
use Tests\TestCase;

class TripNameCompositionTest extends TestCase
{
    public function test_composes_program_and_client_name_when_both_present(): void
    {
        $this->assertSame(
            'Chomoutov - kemp, ZŠ Svatoplukova, Zelenková',
            TripForm::composeName('Chomoutov - kemp', 'ZŠ Svatoplukova, Zelenková')
        );
    }

    public function test_falls_back_to_client_name_alone_when_there_is_no_program(): void
    {
        $this->assertSame('ZŠ Svatoplukova, Zelenková', TripForm::composeName(null, 'ZŠ Svatoplukova, Zelenková'));
    }

    public function test_falls_back_to_program_name_alone_when_there_is_no_client(): void
    {
        $this->assertSame('Chomoutov - kemp', TripForm::composeName('Chomoutov - kemp', null));
    }

    public function test_returns_null_when_both_are_blank(): void
    {
        $this->assertNull(TripForm::composeName(null, ''));
    }
}
