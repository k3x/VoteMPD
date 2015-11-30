<?php

class Tasker {
    private static $jobs = [];

    public static function add($offset, callable $callable, array $parameters = []) {
        $job['time'] = time() + (int)$offset;
        $job['callable'] = $callable;
        $job['parameters'] = $parameters;
        self::$jobs[] = $job;
    }

    public static function doWork() {
        $time = time();
        foreach (self::$jobs as $key => $job) {
            if ($job['time'] <= $time) {
                $job['callable']($job['parameters']);
                unset(self::$jobs[$key]);
            }
        }
    }
}
?>