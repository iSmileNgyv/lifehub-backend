<?php

use App\Http\Controllers\Api\V1\Admin\CardController;
use App\Http\Controllers\Api\V1\Admin\CardTemplateController;
use App\Http\Controllers\Api\V1\Admin\AiCardController;
use App\Http\Controllers\Api\V1\Admin\CashDeskController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\FinanceCategoryController;
use App\Http\Controllers\Api\V1\Admin\FinanceJournalController;
use App\Http\Controllers\Api\V1\Admin\FinanceLedgerController;
use App\Http\Controllers\Api\V1\Admin\FinanceReportController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\DeckController;
use App\Http\Controllers\Api\V1\Admin\StudyController;
use App\Http\Controllers\Api\V1\Admin\ItemController;
use App\Http\Controllers\Api\V1\Admin\ItemMeasurementController;
use App\Http\Controllers\Api\V1\Admin\ItemPriceController;
use App\Http\Controllers\Api\V1\Admin\LanguageController;
use App\Http\Controllers\Api\V1\Admin\MeasurementController;
use App\Http\Controllers\Api\V1\Admin\VehicleController;
use App\Http\Controllers\Api\V1\Admin\VehicleExpenseController;
use App\Http\Controllers\Api\V1\Admin\VehicleFuelController;
use App\Http\Controllers\Api\V1\Admin\VehicleReadingController;
use App\Http\Controllers\Api\V1\Admin\VehicleServiceController;
use App\Http\Controllers\Api\V1\Admin\OperationController;
use App\Http\Controllers\Api\V1\Admin\TradingFormulaController;
use App\Http\Controllers\Api\V1\Admin\TradingJournalController;
use App\Http\Controllers\Api\V1\Admin\TradingJournalEntryController;
use App\Http\Controllers\Api\V1\Admin\RoleAccessController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\FileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::get('registration-status', [AuthController::class, 'registrationStatus']);
        Route::post('register', [AuthController::class, 'register']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('profile', [AuthController::class, 'updateProfile']);
            Route::put('password', [AuthController::class, 'changePassword']);
            Route::put('language', [AuthController::class, 'updateLanguage']);
        });
    });

    // Fayl servisi: yükləmə (auth), göstərmə public (<img> üçün)
    Route::get('files/{file}', [FileController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('files', [FileController::class, 'store']);

        // Dillər: aktiv siyahı hər kəsə (formalar üçün), idarəetmə LANGUAGE_MANAGE ilə
        Route::get('languages', [LanguageController::class, 'index']);
        Route::get('languages/all', [LanguageController::class, 'all'])->middleware('access:LANGUAGE_VIEW');
        Route::post('languages', [LanguageController::class, 'store'])->middleware('access:LANGUAGE_MANAGE');
        Route::patch('languages/{language}', [LanguageController::class, 'update'])->middleware('access:LANGUAGE_MANAGE');

        // Trading — satış formulaları (pilləli)
        Route::get('trading/formulas', [TradingFormulaController::class, 'index'])->middleware('access:TRADING_VIEW');
        Route::post('trading/formulas/compute', [TradingFormulaController::class, 'compute'])->middleware('access:TRADING_VIEW');
        Route::post('trading/formulas', [TradingFormulaController::class, 'store'])->middleware('access:TRADING_FORMULA_MANAGE');
        Route::patch('trading/formulas/{formula}', [TradingFormulaController::class, 'update'])->middleware('access:TRADING_FORMULA_MANAGE');
        Route::delete('trading/formulas/{formula}', [TradingFormulaController::class, 'destroy'])->middleware('access:TRADING_FORMULA_MANAGE');
        Route::put('trading/formulas/{formula}/activate', [TradingFormulaController::class, 'activate'])->middleware('access:TRADING_FORMULA_MANAGE');

        // Trading jurnalları (batch: alış/satış → post)
        Route::get('trading/balance', [TradingJournalController::class, 'balance'])->middleware('access:TRADING_VIEW');
        Route::get('trading/stats', [TradingJournalController::class, 'stats'])->middleware('access:TRADING_VIEW');
        Route::get('trading/journals', [TradingJournalController::class, 'index'])->middleware('access:TRADING_VIEW');
        Route::get('trading/journals/{journal}', [TradingJournalController::class, 'show'])->middleware('access:TRADING_VIEW');
        Route::post('trading/journals', [TradingJournalController::class, 'store'])->middleware('access:TRADING_CREATE');
        Route::patch('trading/journals/{journal}', [TradingJournalController::class, 'update'])->middleware('access:TRADING_UPDATE');
        Route::delete('trading/journals/{journal}', [TradingJournalController::class, 'destroy'])->middleware('access:TRADING_DELETE');
        Route::post('trading/journals/{journal}/post', [TradingJournalController::class, 'post'])->middleware('access:TRADING_POST');
        // Jurnal sətirləri (inline)
        Route::get('trading/journals/{journal}/entries', [TradingJournalEntryController::class, 'index'])->middleware('access:TRADING_VIEW');
        Route::post('trading/journals/{journal}/entries', [TradingJournalEntryController::class, 'store'])->middleware('access:TRADING_UPDATE');
        Route::patch('trading/journals/{journal}/entries/{entry}', [TradingJournalEntryController::class, 'update'])->middleware('access:TRADING_UPDATE');
        Route::delete('trading/journals/{journal}/entries/{entry}', [TradingJournalEntryController::class, 'destroy'])->middleware('access:TRADING_UPDATE');

        // Katalog: ölçü vahidləri
        Route::get('measures', [MeasurementController::class, 'index'])->middleware('access:MEASURE_VIEW');
        Route::post('measures', [MeasurementController::class, 'store'])->middleware('access:MEASURE_CREATE');
        Route::patch('measures/{measurement}', [MeasurementController::class, 'update'])->middleware('access:MEASURE_UPDATE');
        Route::delete('measures/{measurement}', [MeasurementController::class, 'destroy'])->middleware('access:MEASURE_DELETE');

        // Katalog: kateqoriyalar (ağac)
        Route::get('categories', [CategoryController::class, 'index'])->middleware('access:CATEGORY_VIEW');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('access:CATEGORY_CREATE');
        Route::put('categories/reorder', [CategoryController::class, 'reorder'])->middleware('access:CATEGORY_UPDATE');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->middleware('access:CATEGORY_UPDATE');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('access:CATEGORY_DELETE');

        // Maliyyə kateqoriyaları (gəlir/xərc ağacı — item kateqoriyasından ayrı)
        Route::get('finance-categories', [FinanceCategoryController::class, 'index'])->middleware('access:FINCATEGORY_VIEW');
        Route::post('finance-categories', [FinanceCategoryController::class, 'store'])->middleware('access:FINCATEGORY_CREATE');
        Route::put('finance-categories/reorder', [FinanceCategoryController::class, 'reorder'])->middleware('access:FINCATEGORY_UPDATE');
        Route::patch('finance-categories/{financeCategory}', [FinanceCategoryController::class, 'update'])->middleware('access:FINCATEGORY_UPDATE');
        Route::delete('finance-categories/{financeCategory}', [FinanceCategoryController::class, 'destroy'])->middleware('access:FINCATEGORY_DELETE');

        // Maliyyə jurnalı → post → finance_ledger (detal) + cash_ledger (pul)
        Route::get('finance-journals', [FinanceJournalController::class, 'index'])->middleware('access:FINANCE_VIEW');
        Route::post('finance-journals', [FinanceJournalController::class, 'store'])->middleware('access:FINANCE_CREATE');
        Route::get('finance-journals/{financeJournal}', [FinanceJournalController::class, 'showJournal'])->middleware('access:FINANCE_VIEW');
        Route::delete('finance-journals/{financeJournal}', [FinanceJournalController::class, 'destroy'])->middleware('access:FINANCE_DELETE');
        Route::post('finance-journals/{financeJournal}/entries', [FinanceJournalController::class, 'addEntry'])->middleware('access:FINANCE_CREATE');
        Route::patch('finance-journals/{financeJournal}/entries/{entry}', [FinanceJournalController::class, 'updateEntry'])->middleware('access:FINANCE_UPDATE');
        Route::delete('finance-journals/{financeJournal}/entries/{entry}', [FinanceJournalController::class, 'deleteEntry'])->middleware('access:FINANCE_DELETE');
        Route::put('finance-journals/{financeJournal}/entries/{entry}/lines', [FinanceJournalController::class, 'saveLines'])->middleware('access:FINANCE_UPDATE');
        Route::post('finance-journals/{financeJournal}/post', [FinanceJournalController::class, 'post'])->middleware('access:FINANCE_POST');

        // Maliyyə hesabatları (kateqoriya / məhsul / kassa)
        // Post olunmuş ledger — baxış + geri qaytar (reverse) + qeyd/kateqoriya redaktəsi
        Route::get('finance-ledger', [FinanceLedgerController::class, 'index'])->middleware('access:FINANCE_VIEW');
        Route::patch('finance-ledger/{financeLedger}', [FinanceLedgerController::class, 'updatePosted'])->middleware('access:FINANCE_UPDATE');
        Route::patch('finance-ledger/{financeLedger}/lines', [FinanceLedgerController::class, 'saveLines'])->middleware('access:FINANCE_UPDATE');
        Route::post('finance-ledger/{financeLedger}/reverse', [FinanceLedgerController::class, 'reverse'])->middleware('access:FINANCE_POST');

        Route::get('finance-reports/trend', [FinanceReportController::class, 'trend'])->middleware('access:FINANCE_VIEW');
        Route::get('finance-reports/summary', [FinanceReportController::class, 'summary'])->middleware('access:FINANCE_VIEW');
        Route::get('finance-reports/items', [FinanceReportController::class, 'items'])->middleware('access:FINANCE_VIEW');
        Route::get('finance-reports/cash', [FinanceReportController::class, 'cash'])->middleware('access:FINANCE_VIEW');

        // Sistem ayarları
        Route::get('settings', [SettingController::class, 'index'])->middleware('access:SETTINGS_VIEW');
        Route::put('settings', [SettingController::class, 'update'])->middleware('access:SETTINGS_MANAGE');

        // Katalog: məhsullar / ehtiyat hissələr (items)
        Route::get('items', [ItemController::class, 'index'])->middleware('access:PRODUCT_VIEW');
        Route::get('items/by-barcode/{barcode}', [ItemController::class, 'byBarcode'])->middleware('access:PRODUCT_VIEW');
        Route::get('items/{item}/last-prices', [ItemPriceController::class, 'lastPrices'])->middleware('access:PRODUCT_VIEW');
        Route::get('items/{item}/price-history', [ItemPriceController::class, 'history'])->middleware('access:PRODUCT_VIEW');
        Route::post('items', [ItemController::class, 'store'])->middleware('access:PRODUCT_CREATE');
        Route::patch('items/{item}', [ItemController::class, 'update'])->middleware('access:PRODUCT_UPDATE');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->middleware('access:PRODUCT_DELETE');
        // Vahid çevirmələri (1 kisə = 50 kq)
        Route::get('items/{item}/measures', [ItemMeasurementController::class, 'index'])->middleware('access:PRODUCT_VIEW');
        Route::post('items/{item}/measures', [ItemMeasurementController::class, 'store'])->middleware('access:PRODUCT_UPDATE');
        Route::patch('items/{item}/measures/{measurement}', [ItemMeasurementController::class, 'update'])->middleware('access:PRODUCT_UPDATE');
        Route::delete('items/{item}/measures/{measurement}', [ItemMeasurementController::class, 'destroy'])->middleware('access:PRODUCT_UPDATE');

        // Maşınlar (vehicles) + probeq oxunuşları + xidmət qeydləri
        Route::get('vehicles', [VehicleController::class, 'index'])->middleware('access:VEHICLE_VIEW');
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->middleware('access:VEHICLE_VIEW');
        Route::post('vehicles', [VehicleController::class, 'store'])->middleware('access:VEHICLE_CREATE');
        Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('access:VEHICLE_UPDATE');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->middleware('access:VEHICLE_DELETE');
        Route::get('vehicles/{vehicle}/readings', [VehicleReadingController::class, 'index'])->middleware('access:VEHICLE_VIEW');
        Route::post('vehicles/{vehicle}/readings', [VehicleReadingController::class, 'store'])->middleware('access:VEHICLE_UPDATE');
        Route::delete('vehicles/{vehicle}/readings/{reading}', [VehicleReadingController::class, 'destroy'])->middleware('access:VEHICLE_UPDATE');
        Route::post('vehicles/{vehicle}/services', [VehicleServiceController::class, 'store'])->middleware('access:VEHICLE_UPDATE');
        Route::patch('vehicles/{vehicle}/services/{service}', [VehicleServiceController::class, 'update'])->middleware('access:VEHICLE_UPDATE');
        Route::put('vehicles/{vehicle}/services/{service}/close', [VehicleServiceController::class, 'close'])->middleware('access:VEHICLE_UPDATE');
        Route::put('vehicles/{vehicle}/services/{service}/reactivate', [VehicleServiceController::class, 'reactivate'])->middleware('access:VEHICLE_UPDATE');
        Route::delete('vehicles/{vehicle}/services/{service}', [VehicleServiceController::class, 'destroy'])->middleware('access:VEHICLE_UPDATE');
        // Məsrəflər
        Route::get('vehicles/{vehicle}/expenses', [VehicleExpenseController::class, 'index'])->middleware('access:VEHICLE_VIEW');
        Route::post('vehicles/{vehicle}/expenses', [VehicleExpenseController::class, 'store'])->middleware('access:VEHICLE_UPDATE');
        Route::delete('vehicles/{vehicle}/expenses/{expense}', [VehicleExpenseController::class, 'destroy'])->middleware('access:VEHICLE_UPDATE');
        // Yanacaq
        Route::get('vehicles/{vehicle}/fuel', [VehicleFuelController::class, 'index'])->middleware('access:VEHICLE_VIEW');
        Route::post('vehicles/{vehicle}/fuel', [VehicleFuelController::class, 'store'])->middleware('access:VEHICLE_UPDATE');
        Route::delete('vehicles/{vehicle}/fuel/{fuel}', [VehicleFuelController::class, 'destroy'])->middleware('access:VEHICLE_UPDATE');

        // Öyrənmə (flashcards + SM-2)
        // Kart şablonları (formullar) — deck səviyyəsində seçilir
        Route::get('study/templates', [CardTemplateController::class, 'index'])->middleware('access:STUDY_VIEW');
        Route::post('study/templates', [CardTemplateController::class, 'store'])->middleware('access:STUDY_CREATE');
        Route::patch('study/templates/{template}', [CardTemplateController::class, 'update'])->middleware('access:STUDY_UPDATE');
        Route::delete('study/templates/{template}', [CardTemplateController::class, 'destroy'])->middleware('access:STUDY_DELETE');
        // AI ilə kart doldurma (factory: hazırda OpenAI)
        Route::post('study/templates/{template}/generate', [AiCardController::class, 'generate'])->middleware('access:STUDY_CREATE');
        Route::post('study/templates/{template}/generate-bulk', [AiCardController::class, 'generateBulk'])->middleware('access:STUDY_CREATE');

        Route::get('study/decks', [DeckController::class, 'index'])->middleware('access:STUDY_VIEW');
        Route::post('study/decks', [DeckController::class, 'store'])->middleware('access:STUDY_CREATE');
        Route::patch('study/decks/{deck}', [DeckController::class, 'update'])->middleware('access:STUDY_UPDATE');
        Route::delete('study/decks/{deck}', [DeckController::class, 'destroy'])->middleware('access:STUDY_DELETE');
        Route::get('study/decks/{deck}/cards', [CardController::class, 'index'])->middleware('access:STUDY_VIEW');
        Route::post('study/decks/{deck}/cards', [CardController::class, 'store'])->middleware('access:STUDY_CREATE');
        Route::patch('study/decks/{deck}/cards/{card}', [CardController::class, 'update'])->middleware('access:STUDY_UPDATE');
        Route::delete('study/decks/{deck}/cards/{card}', [CardController::class, 'destroy'])->middleware('access:STUDY_DELETE');
        Route::get('study/decks/{deck}/queue', [StudyController::class, 'queue'])->middleware('access:STUDY_VIEW');
        Route::post('study/decks/{deck}/cards/{card}/answer', [StudyController::class, 'answer'])->middleware('access:STUDY_UPDATE');

        // Kassalar (cash desk)
        Route::get('cash-desks', [CashDeskController::class, 'index'])->middleware('access:CASHDESK_VIEW');
        Route::post('cash-desks', [CashDeskController::class, 'store'])->middleware('access:CASHDESK_CREATE');
        Route::patch('cash-desks/{cashDesk}', [CashDeskController::class, 'update'])->middleware('access:CASHDESK_UPDATE');
        Route::delete('cash-desks/{cashDesk}', [CashDeskController::class, 'destroy'])->middleware('access:CASHDESK_DELETE');

        // İstifadəçilər (users modulu)
        Route::get('users', [UserController::class, 'index'])->middleware('access:USER_VIEW');
        Route::post('users', [UserController::class, 'store'])->middleware('access:USER_CREATE');
        Route::patch('users/{user}', [UserController::class, 'update'])->middleware('access:USER_UPDATE');
        Route::put('users/{user}/password', [UserController::class, 'setPassword'])->middleware('access:USER_UPDATE');
        Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('access:USER_ROLE_ASSIGN');

        // Operation kataloqu (rol UI palitrası)
        Route::get('operations', [OperationController::class, 'index'])->middleware('access:ROLE_VIEW');

        // Rollar
        Route::get('roles', [RoleController::class, 'index'])->middleware('access:ROLE_VIEW');
        Route::post('roles', [RoleController::class, 'store'])->middleware('access:ROLE_CREATE');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('access:ROLE_UPDATE');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('access:ROLE_DELETE');

        // Rolun icazə matrisi (drag-drop / qıfıl / zibil)
        Route::get('roles/{role}/access', [RoleAccessController::class, 'index'])->middleware('access:ROLE_VIEW');
        Route::put('roles/{role}/access/{operation}', [RoleAccessController::class, 'upsert'])->middleware('access:ROLE_ACCESS_MANAGE');
        Route::delete('roles/{role}/access/{operation}', [RoleAccessController::class, 'destroy'])->middleware('access:ROLE_ACCESS_MANAGE');
    });
});
