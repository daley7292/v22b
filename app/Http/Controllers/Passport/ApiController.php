<?php

namespace App\Http\Controllers\Passport;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    /**
     * 注册新用户并使用授权令牌调用两个后续的 API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // 获取当前的协议和主机地址
        $baseUrl = $request->getSchemeAndHttpHost();
        $registerEndpoint = '/api/v1/passport/auth/register';
        $registerUrl = $baseUrl . $registerEndpoint;

        // 从请求中接收 `email`、`password` 和 `code`
        $email = $request->input('email');
        $password = $request->input('password');
        $code = $request->input('code');
        $inviteCode = $request->input('invite_code');
        $emailCode = $request->input('email_code');

        // 检查必要字段是否存在
        if (!$email || !$password || !$code) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少必需的参数：email、password 或 code',
            ], 400);
        }

        // 准备发送给注册 API 的请求数据
        $registerRequestData = [
            'email' => $email,
            'password' => $password,
            'invite_code' => $inviteCode,
            'email_code' => $emailCode,
        ];

        // 通过 HTTP 客户端请求注册 API
        $registerResponse = Http::post($registerUrl, $registerRequestData);

        // 检查注册请求是否成功
        if ($registerResponse->successful()) {
            // 获取注册 API 的响应数据
            $registerResponseData = $registerResponse->json();
            $authData = $registerResponseData['data']['auth_data'] ?? null;

            if ($authData) {
                // 调用第二个 API：检查优惠券
                $couponApiUrl = $baseUrl . '/api/v1/user/redemptioncode/check'; // 替换为实际的 API URL
                $couponApiResponse = Http::withHeaders([
                    'Authorization' => $authData,
                ])->post($couponApiUrl, [
                    'code' => $code,
                ]);

                if ($couponApiResponse->successful()) {
                    // 解析第二个 API 的响应内容
                    $couponData = $couponApiResponse->json()['data'];
                    $limitPlanIds = $couponData['limit_plan_ids'][0] ?? null;
                    $limitPeriod = $couponData['limit_period'][0] ?? null;

                    if ($limitPlanIds && $limitPeriod) {
                        // 调用第三个 API：保存订单
                        $orderApiUrl = $baseUrl . '/api/v1/user/order/save'; // 替换为实际的 API URL
                        $orderApiResponse = Http::withHeaders([
                            'Authorization' => $authData,
                        ])->post($orderApiUrl, [
                            'plan_id' => $limitPlanIds,
                            'period' => $limitPeriod,
                            'coupon_code' => $code,
                        ]);

                        // 检查保存订单请求是否成功
                        if ($orderApiResponse->successful()) {
                            // 获取保存订单 API 的响应数据
                            $orderResponseData = $orderApiResponse->json();
                            $tradeNo = $orderResponseData['data'] ?? null;

                            if ($tradeNo) {
                                // 调用第四个 API：结算订单
                                $checkoutApiUrl = $baseUrl . '/api/v1/user/order/checkout'; // 替换为实际的 API URL
                                $checkoutApiResponse = Http::withHeaders([
                                    'Authorization' => $authData,
                                ])->post($checkoutApiUrl, [
                                    'trade_no' => $tradeNo,
                                ]);

                                // 检查结算订单请求是否成功
                                if ($checkoutApiResponse->successful()) {
                                    // 获取结算订单 API 的响应数据
                                    $checkoutResponseData = $checkoutApiResponse->json();
                                    $checkoutSuccess = $checkoutResponseData['type'] == -1 && $checkoutResponseData['data'] === true;

                                    if ($checkoutSuccess) {
                                        // 返回完整的四次 API 结果
                                        return response()->json([
                                            'status' => 'success',
                                            'registration' => $registerResponseData,
                                            'couponCheck' => $couponApiResponse->json(),
                                            'orderSave' => $orderResponseData,
                                            'orderCheckout' => $checkoutResponseData,
                                        ]);
                                    } else {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => '订单结算失败',
                                            'details' => $checkoutApiResponse->json(),
                                        ], $checkoutApiResponse->status());
                                    }
                                } else {
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => '订单结算请求失败',
                                        'details' => $checkoutApiResponse->json(),
                                    ], $checkoutApiResponse->status());
                                }
                            } else {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => '保存订单响应中缺少 trade_no',
                                ], 500);
                            }
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => '保存订单失败',
                                'details' => $orderApiResponse->json(),
                            ], $orderApiResponse->status());
                        }
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => '无法从优惠券检查中解析 limit_plan_ids 或 limit_period',
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => '优惠券检查失败',
                        'details' => $couponApiResponse->json(),
                    ], $couponApiResponse->status());
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => '注册响应中缺少 auth_data',
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => '注册失败',
                'details' => $registerResponse->json(),
            ], $registerResponse->status());
        }
    }

    public function validateCouponAndSaveOrder(Request $request)
    {
        // 获取请求中的Authorization头
        $authorization = $request->header('Authorization');

        // 验证Authorization是否存在
        if (!$authorization) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少 Authorization 头',
            ], 400);
        }

        // 获取请求体中的优惠码
        $code = $request->input('code');
        if (!$code) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少必需的参数：code',
            ], 400);
        }

        // 构建优惠码验证API的URL
        $baseUrl = $request->getSchemeAndHttpHost();
        $couponApiUrl = $baseUrl . '/api/v1/user/redemptioncode/check';

        // 调用优惠码验证API
        $couponApiResponse = $this->apiRequest($couponApiUrl, ['code' => $code], $authorization);

        // 检查优惠码验证结果
        if (!$couponApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '优惠码验证失败',
                'details' => $couponApiResponse->json(),
            ], $couponApiResponse->status());
        }

        // 获取优惠码验证成功后的数据，如 plan_id 和 period
        $couponData = $couponApiResponse->json()['data'];
        $planId = $couponData['limit_plan_ids'][0] ?? null;
        $period = $couponData['limit_period'][0] ?? null;

        // 验证获取到的 plan_id 和 period
        if (!$planId || !$period) {
            return response()->json([
                'status' => 'error',
                'message' => '优惠码响应中缺少有效的 plan_id 或 period',
            ], 500);
        }

        // 构建保存订单API的URL
        $orderApiUrl = $baseUrl . '/api/v1/user/order/save';

        // 调用保存订单API
        $orderApiResponse = $this->apiRequest($orderApiUrl, [
            'plan_id' => $planId,
            'period' => $period,
            'coupon_code' => $code
        ], $authorization);

        // 检查保存订单请求是否成功
        if (!$orderApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '保存订单失败',
                'details' => $orderApiResponse->json(),
            ], $orderApiResponse->status());
        }

        // 解析订单保存成功后的数据
        $orderData = $orderApiResponse->json()['data'];
        $tradeNo = $orderData ?? null;

        // 构建结算订单API的URL
        $checkoutApiUrl = $baseUrl . '/api/v1/user/order/checkout';

        // 调用结算订单API
        $checkoutApiResponse = $this->apiRequest($checkoutApiUrl, ['trade_no' => $tradeNo], $authorization);

        // 检查结算订单请求是否成功
        if (!$checkoutApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '结算订单失败',
                'details' => $checkoutApiResponse->json(),
            ], $checkoutApiResponse->status());
        }

        // 返回成功响应
        return response()->json([
            'status' => 'success',
            'couponCheck' => $couponApiResponse->json(),
            'orderSave' => $orderApiResponse->json(),
            'orderCheckout' => $checkoutApiResponse->json(),
        ]);
    }


    /**
     * 统一的API请求处理
     *
     * @param string $url
     * @param array $data
     * @param string $authData
     * @return \Illuminate\Http\Client\Response
     */
    protected function apiRequest($url, array $data, $authData)
    {
        try {
            $response = Http::withHeaders(['Authorization' => $authData])->post($url, $data);

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'API 请求异常',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /*
     * 新增兑换接口一体化注册
     */
    public function unificationReg()
    {


    }


}
