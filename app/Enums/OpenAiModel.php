<?php

namespace App\Enums;

enum OpenAiModel: string
{
    case GPT4O = 'gpt-4o';
    case GPT4O_MINI = 'gpt-4o-mini';
    case GPT4 = 'gpt-4';
    case GPT4_TURBO = 'gpt-4-turbo';
    case GPT3_5_TURBO = 'gpt-3.5-turbo';
}
