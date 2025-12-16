<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;

class TemplateUpdateException extends Exception
{
    protected $code = 422;
    
    public function __construct($message = "Error actualizando plantilla", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    
    public function render($request)
    {
        return response()->json([
            'error' => 'template_update_error',
            'message' => $this->getMessage()
        ], $this->code);
    }
}