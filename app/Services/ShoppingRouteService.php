<?php

namespace App\Services;

use App\Enums\SelloutRisk;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\ShoppingRoute;
use App\Models\User;
use App\Support\BoothSorter;
use Illuminate\Support\Collection;

/**
 * 当日の巡回順。
 *
 * 既定は「完売しやすいサークルを先に → あとは配置順（会場を往復しない順）」。
 * 本人が並べ替えた場合はその順を優先する。
 */
class ShoppingRouteService
{
    /**
     * 既定の巡回順で並べ替える。
     *
     * @param  Collection<int, array<string, mixed>>  $rows  各要素に 'circle' を持つ
     * @return Collection<int, array<string, mixed>>
     */
    public function sortByDefault(Collection $rows): Collection
    {
        return $rows->sortBy(function (array $row) {
            /** @var EventCircle|null $circle */
            $circle = $row['circle'] ?? null;
            $risk = $circle?->sellout_risk;

            return sprintf(
                '%d|%s',
                $risk instanceof SelloutRisk ? $risk->order() : SelloutRisk::Medium->order(),
                BoothSorter::key($circle?->booth)
            );
        })->values();
    }

    /**
     * 保存済みの巡回順を適用する。並べ替えていないサークルは末尾に既定順で続ける。
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function apply(Collection $rows, Event $event, User $user): Collection
    {
        $saved = $this->savedOrder($event, $user);

        if ($saved === []) {
            return $this->sortByDefault($rows);
        }

        $position = array_flip($saved);
        $fallback = $this->sortByDefault($rows)->values();
        $fallbackPosition = [];

        foreach ($fallback as $index => $row) {
            $fallbackPosition[$this->circleIdOf($row)] = $index;
        }

        return $rows->sortBy(function (array $row) use ($position, $fallbackPosition) {
            $id = $this->circleIdOf($row);

            // 保存済みの順に無いサークル（あとから担当になった等）は末尾へ
            return isset($position[$id])
                ? sprintf('0|%06d', $position[$id])
                : sprintf('1|%06d', $fallbackPosition[$id] ?? 0);
        })->values();
    }

    /**
     * 保存済みの巡回順（サークルID）。
     *
     * @return array<int, int>
     */
    public function savedOrder(Event $event, User $user): array
    {
        return ShoppingRoute::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first()
            ?->order() ?? [];
    }

    /**
     * 巡回順を保存する。
     *
     * @param  array<int, int|string>  $circleIds
     */
    public function save(Event $event, User $user, array $circleIds): void
    {
        $circleIds = array_values(array_unique(array_map('intval', $circleIds)));

        if (count($circleIds) > 500) {
            throw new BusinessRuleException('サークルが多すぎます。', 'route');
        }

        // このイベントのサークルだけを受け付ける
        $valid = $event->eventCircles()->whereIn('id', $circleIds)->pluck('id')->all();
        $ordered = array_values(array_filter($circleIds, fn (int $id) => in_array($id, $valid, true)));

        if ($ordered === []) {
            throw new BusinessRuleException('並べ替えの対象が見つかりません。', 'route');
        }

        ShoppingRoute::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['circle_order' => $ordered],
        );
    }

    /**
     * 並べ替えを取り消して既定順に戻す。
     */
    public function reset(Event $event, User $user): void
    {
        ShoppingRoute::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * 参加者間で共有するためのテキスト。
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function shareText(Event $event, User $user, Collection $rows): string
    {
        $lines = ['【'.$event->name.'】'.$user->displayName().' さんの巡回ルート', ''];
        $number = 0;

        foreach ($rows as $row) {
            /** @var EventCircle|null $circle */
            $circle = $row['circle'] ?? null;

            if ($circle === null) {
                continue;
            }

            $number++;
            $risk = $circle->sellout_risk;
            $mark = $risk instanceof SelloutRisk && $risk === SelloutRisk::High ? '（早めに）' : '';

            $lines[] = sprintf('%d. %s %s%s', $number, $circle->locationLabel(), $circle->display_name, $mark);

            foreach ($row['items'] ?? [] as $item) {
                $product = $item['item']->eventProduct ?? null;

                if ($product === null) {
                    continue;
                }

                $lines[] = sprintf('    - %s × %d点', $product->name, $item['myQuantity'] ?? 0);
            }
        }

        if ($number === 0) {
            $lines[] = '（回るサークルはありません）';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function circleIdOf(array $row): int
    {
        return (int) ($row['circle']?->id ?? 0);
    }
}
