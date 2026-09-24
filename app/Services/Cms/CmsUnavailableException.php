<?php

namespace App\Services\Cms;

use RuntimeException;

/** The CMS could not be reached and there is no cached copy to fall back to. */
class CmsUnavailableException extends RuntimeException {}
