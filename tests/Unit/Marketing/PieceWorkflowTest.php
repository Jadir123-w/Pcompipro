<?php

namespace Tests\Unit\Marketing;

use Modules\Marketing\Models\Piece;
use Tests\TestCase;

class PieceWorkflowTest extends TestCase
{
    public function test_brief_is_mass_assignable()
    {
        $piece = new Piece(['texto_en_imagen_video' => 'Texto sobre la imagen']);

        $this->assertSame('Texto sobre la imagen', $piece->texto_en_imagen_video);
    }

    public function test_piece_states_match_the_supported_workflow()
    {
        $this->assertSame([
            'pendiente',
            'por_revisar',
            'aprobado',
            'publicado',
            'observada',
            'programado',
        ], Piece::ESTADOS);
    }

}
