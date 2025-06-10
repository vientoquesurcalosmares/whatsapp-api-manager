<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;

class TemplateComponentException extends Exception
{
    protected $code = 422;
    
    public function __construct($message = "Error en componente de plantilla", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    
    public function render($request)
    {
        return response()->json([
            'error' => 'template_component_error',
            'message' => $this->getMessage()
        ], $this->code);
    }
}