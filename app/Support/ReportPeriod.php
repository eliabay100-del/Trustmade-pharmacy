<?php

namespace App\Support;

use App\Models\IssuedItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The month / year a report is being read for.
 *
 * Both the Sales and the Financial report take the same filter, so it lives
 * here rather than being re-derived per controller - otherwise the two
 * screens drift apart the first time someone edits one of them.
 *
 * A month on its own means the current year: nobody wants "every March since
 * we opened". A year on its own covers all twelve months. Both blank means
 * all time, which is what these screens showed before the filter existed.
 *
 * Values are sanitised rather than validated. A hand-edited URL saying
 * month=13 should quietly mean "all months", not bounce the reader off the
 * report with a validation error.
 */
class ReportPeriod
{
    public const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function __construct(
        public readonly ?int $month,
        public readonly ?int $year,
        public readonly ?int $branchId = null,
    ) {}

    public static function fromRequest(Request $request, ?int $branchId = null): self
    {
        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;

        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : null;

        if ($month && ! $year) {
            $year = (int) now()->year;
        }

        return new self($month, $year, $branchId);
    }

    public function isFiltered(): bool
    {
        return $this->month !== null || $this->year !== null;
    }

    /**
     * Bounds for a whereBetween on a date column, or null for all time.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function range(): ?array
    {
        if ($this->month && $this->year) {
            $start = Carbon::create($this->year, $this->month, 1)->startOfDay();

            return [$start, $start->copy()->endOfMonth()->endOfDay()];
        }

        if ($this->year) {
            return [
                Carbon::create($this->year, 1, 1)->startOfDay(),
                Carbon::create($this->year, 12, 31)->endOfDay(),
            ];
        }

        return null;
    }

    /**
     * The last moment the period covers - what a balance sheet is a snapshot
     * of. Null means "right now", the unfiltered case.
     */
    public function end(): ?Carbon
    {
        $range = $this->range();

        return $range ? $range[1] : null;
    }

    /**
     * Plain-English name for the card headings, so a figure on screen always
     * says which period produced it.
     */
    public function label(): string
    {
        if ($this->month && $this->year) {
            return self::MONTH_NAMES[$this->month] . ' ' . $this->year;
        }

        if ($this->month) {
            return self::MONTH_NAMES[$this->month] . ' (all years)';
        }

        if ($this->year) {
            return 'All of ' . $this->year;
        }

        return 'All time';
    }

    /**
     * Every year from the first sale up to this one, newest first.
     *
     * Derived from MIN(issue_date) rather than a DISTINCT YEAR(...) so it
     * behaves identically on MySQL and on the SQLite the test suite runs on.
     */
    public function years(): array
    {
        $firstSale = IssuedItem::query()
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->min('issue_date');

        $currentYear = (int) now()->year;
        $firstYear = $firstSale ? (int) Carbon::parse($firstSale)->year : $currentYear;

        return range($currentYear, max(2000, min($firstYear, $currentYear)));
    }
}
