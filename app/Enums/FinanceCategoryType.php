<?php

namespace App\Enums;

/** Maliyyə kateqoriyasının növü — gəlir yoxsa xərc (kök rolu). */
enum FinanceCategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
