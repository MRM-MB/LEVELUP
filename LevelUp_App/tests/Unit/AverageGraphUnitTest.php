<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AverageGraphUnitTest extends TestCase{

    
    // Test average graph status calculation for 'No Data'
    public function test_average_graph_status_no_data(): void
    {
        $avgSitting = 0;
        $avgStanding = 0;

        $sessionTotal = max($avgSitting + $avgStanding, 0);
        $standingShare = $sessionTotal > 0 ? ($avgStanding / $sessionTotal) * 100 : 0;
        $sittingShare = max(0, 100 - $standingShare);

        if ($avgSitting == 0 && $avgStanding == 0) {
            $status = 'No Data';
        }

        $this->assertEquals('No Data', $status);
        $this->assertEquals(0, $standingShare);
        $this->assertEquals(100, $sittingShare);
    }

    // Test average graph status calculation for 'Highly Active'
    public function test_average_graph_status_highly_active(): void
    {
        $avgSitting = 20;
        $avgStanding = 25;
        $normalizedStanding = $avgSitting > 0 ? ($avgStanding / $avgSitting) * 20 : 0;

        if ($normalizedStanding > 15) {
            $status = 'Highly Active';
        }

        $this->assertEquals('Highly Active', $status);
        $this->assertTrue($normalizedStanding > 15);
    }

    // Test average graph ratio calculation.
    public function test_average_graph_ratio_calculation(): void
    {
        $avgSitting = 20;
        $avgStanding = 10;
        $normalizedStanding = $avgSitting > 0 ? ($avgStanding / $avgSitting) * 20 : 0;

        $this->assertEquals(10, $normalizedStanding);
        $this->assertEquals('20 : 10', '20 : ' . $normalizedStanding);
    }
}