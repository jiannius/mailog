<?php

use Jiannius\Mailog\Mailog;

if (! function_exists('mailog')) {
    /**
     * Resolve the Mailog singleton.
     */
    function mailog(): Mailog
    {
        return app('mailog');
    }
}
