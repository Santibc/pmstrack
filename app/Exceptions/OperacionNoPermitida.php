<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de negocio impide la operación. El mensaje se muestra al usuario
 * (web) o se devuelve como error 422 (API).
 */
final class OperacionNoPermitida extends RuntimeException {}
