<?php

namespace Tests\Unit\Support;

use App\Support\BoothSorter;
use PHPUnit\Framework\TestCase;

class BoothSorterTest extends TestCase
{
    public function test_sorts_by_hall_block_and_number(): void
    {
        $sorted = BoothSorter::sort([
            '西2 サ-05b',
            '東1 ア-12a',
            '東1 ア-03b',
            '東2 ウ-31a',
            '東1 イ-01a',
        ]);

        $this->assertSame([
            '東1 ア-03b',
            '東1 ア-12a',
            '東1 イ-01a',
            '東2 ウ-31a',
            '西2 サ-05b',
        ], $sorted);
    }

    public function test_unset_booths_come_last(): void
    {
        $sorted = BoothSorter::sort([null, '東1 ア-01a', '']);

        $this->assertSame('東1 ア-01a', $sorted[0]);
    }

    public function test_full_width_input_is_normalised(): void
    {
        $this->assertSame(
            BoothSorter::key('東1 ア-12a'),
            BoothSorter::key('東１　ア－１２ａ')
        );
    }

    public function test_sub_letter_orders_a_before_b(): void
    {
        $sorted = BoothSorter::sort(['東1 ア-12b', '東1 ア-12a']);

        $this->assertSame(['東1 ア-12a', '東1 ア-12b'], $sorted);
    }
}
