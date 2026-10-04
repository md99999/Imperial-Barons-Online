<?php
if (!defined('ABSPATH')) exit;

/**
 * Breadth-first search over the (directed) warp graph.
 */
class IB_Pathfinder {
    private static $graph = null;

    /** @return array sector => int[] outgoing warps */
    public static function graph() {
        if (self::$graph === null) {
            global $wpdb;
            self::$graph = [];
            $rows = $wpdb->get_results('SELECT from_sector, to_sector FROM ' . IB_DB::t('warps'), ARRAY_N);
            foreach ($rows as $r) {
                self::$graph[(int) $r[0]][] = (int) $r[1];
            }
        }
        return self::$graph;
    }

    public static function flush() {
        self::$graph = null;
    }

    /**
     * Shortest path from $start to $end inclusive, or [] if unreachable.
     * $graph defaults to the live universe.
     */
    public static function shortestPath($graph, int $start, int $end): array {
        if ($graph === null) $graph = self::graph();
        if ($start === $end) return [$start];
        $prev = [$start => 0];
        $queue = new SplQueue();
        $queue->enqueue($start);
        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            if (empty($graph[$node])) continue;
            foreach ($graph[$node] as $next) {
                if (isset($prev[$next])) continue;
                $prev[$next] = $node;
                if ($next === $end) {
                    $path = [$end];
                    while ($path[0] !== $start) array_unshift($path, $prev[$path[0]]);
                    return $path;
                }
                $queue->enqueue($next);
            }
        }
        return [];
    }

    /** @return array sector => hop distance, for every sector reachable from $start. */
    /**
     * How dangerous a route looks, as a level from 0 (quiet) to 4 (severe), without saying what
     * the danger is. The reading comes from three things a navigator could reasonably judge:
     * fighters deployed along the way that are not yours, sectors on the route you have never
     * charted, and how far outside the Crown's Peace the course runs. It deliberately does not
     * say whose fighters, how many, or where: the pilot who wants that can sweep the sector.
     *
     * @param  object $p
     * @param  array  $path sector ids, as shortestPath() returns them
     * @return array ['level' => 0-4, 'label' => string]
     */
    public static function route_risk($p, array $path): array {
        if (count($path) < 2) return ['level' => 0, 'label' => self::RISK_LABELS[0]];
        $ahead = array_slice($path, 1);                       // where you are now is not a risk
        $hostiles = IB_Combat::hostiles_by_sector($p, $ahead);
        $charted = array_flip(IB_Player::charted_ids($p->id));

        $score = 0.0;
        foreach ($ahead as $sector) {
            $sector = (int) $sector;
            if (IB_Game::is_core($sector)) continue;           // the Crown's Peace holds here
            $score += 0.25;                                    // simply being outside the Core
            if (!isset($charted[$sector])) $score += 0.5;      // flying blind
            if (!empty($hostiles[$sector])) {
                $score += 1.5 + min(3.0, $hostiles[$sector] / 200);
            }
        }

        $level = 0;
        foreach ([1.0, 2.5, 5.0, 9.0] as $i => $threshold) {
            if ($score >= $threshold) $level = $i + 1;
        }
        return ['level' => $level, 'label' => self::RISK_LABELS[$level]];
    }

    const RISK_LABELS = [0 => 'Quiet', 1 => 'Low', 2 => 'Moderate', 3 => 'High', 4 => 'Severe'];

    public static function distances($graph, int $start, int $max_depth = PHP_INT_MAX): array {
        if ($graph === null) $graph = self::graph();
        $dist = [$start => 0];
        $queue = new SplQueue();
        $queue->enqueue($start);
        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            if ($dist[$node] >= $max_depth || empty($graph[$node])) continue;
            foreach ($graph[$node] as $next) {
                if (isset($dist[$next])) continue;
                $dist[$next] = $dist[$node] + 1;
                $queue->enqueue($next);
            }
        }
        return $dist;
    }

    /** Reverses a directed graph (used to check strong connectivity). */
    public static function reverse(array $graph): array {
        $rev = [];
        foreach ($graph as $from => $tos) {
            foreach ($tos as $to) $rev[$to][] = $from;
        }
        return $rev;
    }
}
