<?php

namespace App\Mcp;

use RuntimeException;

/**
 * Thrown by a tool when the request is understood but can't be done
 * (unknown order, illegal status change). The message goes back to the
 * model as an isError result so it can correct itself.
 */
class ToolException extends RuntimeException {}
