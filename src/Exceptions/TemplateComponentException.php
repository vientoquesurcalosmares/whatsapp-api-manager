<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;

class TemplateComponentException extends Exception
{
    protected $code = 422;

    public function __construct($message = null, $code = 0, ?Exception $previous = null)
    {
        $message = $message ?? whatsapp_trans('exceptions.template_component_error');
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