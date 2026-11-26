<?php // Support/Json.php

namespace Nedieyassin\MessagingGateway\Support;

final class Json
{
    /**
     * Always a JSON *object*. A plain json_encode() turns [] into "[]" and [0 => 'x'] into ["x"],
     * which the gateway rejects for params and button_params.
     * @throws \JsonException
     */
    public static function object(array $values): string
    {
        return json_encode((object)array_map('strval', $values), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}