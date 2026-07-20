<?php

namespace WPSocialReviews\Framework\Container;

use Exception;
use WPSocialReviews\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
