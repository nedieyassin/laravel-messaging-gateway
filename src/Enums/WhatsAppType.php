<?php // Enums/WhatsAppType.php

namespace Nedieyassin\MessagingGateway\Enums;

enum WhatsAppType: string
{
    case TEXT = 'text';
    case TEMPLATE = 'template';
    case DOCUMENT = 'document';
}