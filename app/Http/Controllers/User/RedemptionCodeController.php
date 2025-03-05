<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use App\Services\RedemptionCodeService;
use Illuminate\Http\Request;
use App\Models\Coupon;

class RedemptionCodeController extends Controller
{
    public function check(Request $request)
    {
        if (empty($request->input('code'))) {
            abort(500, __('Coupon cannot be empty'));
        }
        $couponService = new RedemptionCodeService($request->input('code'));
        $couponService->setPlanId($request->input('plan_id'));
        $couponService->setUserId($request->user['id']);
        $couponService->check();
        return response([
            'data' => $couponService->getCoupon()
        ]);
    }
}
