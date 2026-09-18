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
