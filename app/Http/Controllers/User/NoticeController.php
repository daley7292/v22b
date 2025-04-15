<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notice;
use App\Utils\Helper;

class NoticeController extends Controller
{
    public function fetch(Request $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = 5;
        $model = Notice::orderBy('created_at', 'DESC')
            ->where('show', 1);
        $total = $model->count();
        $res = $model->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    /*
     * 获取新的弹窗信息
     */
    public function getPopMessage(Request $request)
    {
        $windowsType = $request->input('windows_type');
        if (!$windowsType) {
            return response(['error' => 'windows_type is required'], 400);
        }
        $res = Notice::orderBy('created_at', 'DESC')
            ->where('show', 1)
            ->where('windows_type', $windowsType)
            ->whereJsonContains('tags', '弹窗')
            ->get();
        return response([
            'data' => $res
        ]);
    }

}

