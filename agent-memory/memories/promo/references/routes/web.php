<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return to_route('login');
});

Route::middleware(['auth', 'twofactor'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

    Route::prefix('setting')->group(function () {
        Route::prefix('general')->group(function () {
            Route::get('/', [\App\Http\Controllers\Setting\GeneralController::class, 'index'])->name('setting.general');
            Route::post('/', [\App\Http\Controllers\Setting\GeneralController::class, 'update'])->name('setting.general.update');
        });

        Route::prefix('activity')->group(function () {
            Route::get('/', [ActivityController::class, 'index'])->name('setting.activity.index');
            Route::get('/export', [ActivityController::class, 'export'])->name('setting.activity.export');
            Route::prefix('/{activity}')->group(function () {
                Route::get('/', [ActivityController::class, 'show'])->name('setting.activity.show');
            });
        });
    });

    Route::prefix('management')->name('management.')->group(function () {
        Route::prefix('pages')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\PageController::class, 'index'])->name('pages.index');
            Route::get('/create', [\App\Http\Controllers\Management\PageController::class, 'create'])->name('pages.create');
            Route::post('/store', [\App\Http\Controllers\Management\PageController::class, 'store'])->name('pages.store');

            Route::prefix('details/{page}')->group(function () {
                Route::get('/edit', [\App\Http\Controllers\Management\PageController::class, 'edit'])->name('pages.edit');
                Route::put('/edit', [\App\Http\Controllers\Management\PageController::class, 'update'])->name('pages.update');
                Route::delete('/delete', [\App\Http\Controllers\Management\PageController::class, 'delete'])->name('pages.delete');
                Route::get('/show', [\App\Http\Controllers\Management\PageController::class, 'show'])->name('pages.show');
                Route::get('/preview', [\App\Http\Controllers\Management\PageController::class, 'preview'])->name('pages.preview');
                Route::patch('/toggle-status', [\App\Http\Controllers\Management\PageController::class, 'toggleStatus'])->name('pages.toggle-status');
            });
        });
        Route::patch('pages-reorder', [\App\Http\Controllers\Management\PageController::class, 'reorder'])->name('pages.reorder');
    });

    Route::prefix('rewards')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\RewardController::class, 'index'])->name('rewards.index');
        Route::get('/create', [\App\Http\Controllers\Management\RewardController::class, 'create'])->name('rewards.create');
        Route::post('/store', [\App\Http\Controllers\Management\RewardController::class, 'store'])->name('rewards.store');

        Route::prefix('details/{reward}')->group(function () {
            Route::get('/edit', [\App\Http\Controllers\Management\RewardController::class, 'edit'])->name('rewards.edit');
            Route::put('/edit', [\App\Http\Controllers\Management\RewardController::class, 'update'])->name('rewards.update');
            Route::delete('/delete', [\App\Http\Controllers\Management\RewardController::class, 'delete'])->name('rewards.delete');
            Route::get('/show', [\App\Http\Controllers\Management\RewardController::class, 'show'])->name('rewards.show');
        });
    });

    Route::prefix('sliders')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\SliderController::class, 'index'])->name('sliders.index');
        Route::get('/create', [\App\Http\Controllers\Management\SliderController::class, 'create'])->name('sliders.create');
        Route::post('/store', [\App\Http\Controllers\Management\SliderController::class, 'store'])->name('sliders.store');

        Route::prefix('details/{slider}')->group(function () {
            Route::get('/edit', [\App\Http\Controllers\Management\SliderController::class, 'edit'])->name('sliders.edit');
            Route::put('/edit', [\App\Http\Controllers\Management\SliderController::class, 'update'])->name('sliders.update');
            Route::delete('/delete', [\App\Http\Controllers\Management\SliderController::class, 'delete'])->name('sliders.delete');
            Route::get('/show', [\App\Http\Controllers\Management\SliderController::class, 'show'])->name('sliders.show');
        });
    });

    Route::prefix('members')->group(function () {
        Route::get('/', [\App\Http\Controllers\MemberController::class, 'index'])->name('members.index');

        Route::prefix('details/{member}')->group(function () {
            Route::get('/show', [\App\Http\Controllers\MemberController::class, 'show'])->name('members.show');
            Route::post('/block', [\App\Http\Controllers\MemberController::class, 'block'])->name('members.block');
            Route::post('/unblock', [\App\Http\Controllers\MemberController::class, 'unblock'])->name('members.unblock');
        });
    });

    Route::prefix('promos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\PromoController::class, 'index'])->name('promos.index');
        Route::get('/create', [\App\Http\Controllers\Management\PromoController::class, 'create'])->name('promos.create');
        Route::post('/store', [\App\Http\Controllers\Management\PromoController::class, 'store'])->name('promos.store');

        Route::prefix('details/{promo}')->group(function () {
            Route::get('/edit', [\App\Http\Controllers\Management\PromoController::class, 'edit'])->name('promos.edit');
            Route::put('/edit', [\App\Http\Controllers\Management\PromoController::class, 'update'])->name('promos.update');
            Route::delete('/delete', [\App\Http\Controllers\Management\PromoController::class, 'delete'])->name('promos.delete');
            Route::get('/show', [\App\Http\Controllers\Management\PromoController::class, 'show'])->name('promos.show');

            // Voucher routes
            Route::get('/vouchers', [\App\Http\Controllers\Management\PromoController::class, 'vouchers'])->name('promos.vouchers');
            Route::post('/vouchers/import', [\App\Http\Controllers\Management\PromoController::class, 'importVouchers'])->name('promos.vouchers.import');
            Route::get('/vouchers/template', [\App\Http\Controllers\Management\PromoController::class, 'downloadVoucherTemplate'])->name('promos.vouchers.template');
        });
    });

    Route::prefix('promo-categories')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\PromoCategoryController::class, 'index'])->name('promo-categories.index');
        Route::get('/create', [\App\Http\Controllers\Management\PromoCategoryController::class, 'create'])->name('promo-categories.create');
        Route::post('/store', [\App\Http\Controllers\Management\PromoCategoryController::class, 'store'])->name('promo-categories.store');

        Route::prefix('details/{promoCategory}')->group(function () {
            Route::get('/edit', [\App\Http\Controllers\Management\PromoCategoryController::class, 'edit'])->name('promo-categories.edit');
            Route::put('/edit', [\App\Http\Controllers\Management\PromoCategoryController::class, 'update'])->name('promo-categories.update');
            Route::delete('/delete', [\App\Http\Controllers\Management\PromoCategoryController::class, 'delete'])->name('promo-categories.delete');
            Route::get('/show', [\App\Http\Controllers\Management\PromoCategoryController::class, 'show'])->name('promo-categories.show');
        });
    });

    Route::prefix('campaigns')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/create', [\App\Http\Controllers\Management\CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/store', [\App\Http\Controllers\Management\CampaignController::class, 'store'])->name('campaigns.store');

        Route::prefix('details/{campaign}')->group(function () {
            Route::get('/edit', [\App\Http\Controllers\Management\CampaignController::class, 'edit'])->name('campaigns.edit');
            Route::put('/edit', [\App\Http\Controllers\Management\CampaignController::class, 'update'])->name('campaigns.update');
            Route::delete('/delete', [\App\Http\Controllers\Management\CampaignController::class, 'delete'])->name('campaigns.delete');
            Route::get('/show', [\App\Http\Controllers\Management\CampaignController::class, 'show'])->name('campaigns.show');
        });
    });

    Route::prefix('management')->group(function () {
        Route::prefix('role')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\RolesController::class, 'index'])->name('management.roles.index');
            Route::get('/create', [\App\Http\Controllers\Management\RolesController::class, 'create'])->name('management.roles.create');
            Route::post('/store', [\App\Http\Controllers\Management\RolesController::class, 'store'])->name('management.roles.store');

            Route::prefix('details/{role}')->group(function () {
                Route::get('/edit', [\App\Http\Controllers\Management\RolesController::class, 'edit'])->name('management.roles.edit');
                Route::put('/update', [\App\Http\Controllers\Management\RolesController::class, 'update'])->name('management.roles.update');
                Route::delete('/delete', [\App\Http\Controllers\Management\RolesController::class, 'delete'])->name('management.roles.delete');
            });
        });
        Route::prefix('user')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\UserController::class, 'index'])->name('management.user.index');
            Route::get('/create', [\App\Http\Controllers\Management\UserController::class, 'create'])->name('management.user.create');
            Route::post('/store', [\App\Http\Controllers\Management\UserController::class, 'store'])->name('management.user.store');

            Route::prefix('details/{user}')->group(function () {
                Route::get('/edit', [\App\Http\Controllers\Management\UserController::class, 'edit'])->name('management.user.edit');
                Route::put('/edit', [\App\Http\Controllers\Management\UserController::class, 'update'])->name('management.user.update');
                Route::delete('/delete', [\App\Http\Controllers\Management\UserController::class, 'delete'])->name('management.user.delete');
            });
        });
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::prefix('upload')->group(function () {
        Route::post('file', [\App\Http\Controllers\UploadController::class, 'file'])->name('upload.file');
        Route::get('file/{file}', [\App\Http\Controllers\UploadController::class, 'show'])->name('file.show');
        Route::post('filepond', [\App\Http\Controllers\UploadController::class, 'filepond'])->name('upload.filepond');
        Route::post('filepond-delete', [\App\Http\Controllers\UploadController::class, 'deleteFilepond'])->name('upload.filepond.delete');
    });

    Route::prefix('activity')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('activity');
        Route::get('/export', [ActivityController::class, 'export'])->name('activity.export');
        Route::prefix('details/{activity}')->group(function () {
            Route::get('/', [ActivityController::class, 'show'])->name('activity.show');
        });
    });

    Route::prefix('media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('media.index');
        Route::get('/fetch', [MediaController::class, 'fetchMedia'])->name('media.fetch');
        Route::get('/date-filters', [MediaController::class, 'fetchDate'])->name('media.date-filters');
        Route::get('/show/{file}', [MediaController::class, 'show'])->name('media.show');
        Route::put('/update/{file}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/delete/{file}', [MediaController::class, 'delete'])->name('media.delete');
        Route::post('/edit/{file}', [UploadController::class, 'updateMedia'])->name('media.edit');
    });
});

require __DIR__.'/auth.php';
