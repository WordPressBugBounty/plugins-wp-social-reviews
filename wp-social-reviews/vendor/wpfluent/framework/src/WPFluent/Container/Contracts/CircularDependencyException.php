<?php

namespace WPSocialReviews\Framework\Container\Contracts;

use Exception;
use WPSocialReviews\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
