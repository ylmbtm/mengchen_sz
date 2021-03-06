<?php

namespace App\Http\Controllers\Admin\Game;

use App\Exceptions\CustomException;
use App\Exceptions\GameApiServiceException;
use App\Http\Requests\AdminRequest;
use App\Services\Game\GameApiService;
use App\Services\Game\GameOptionsService;
use App\Traits\GameTypeMap;
use App\Services\Paginator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\OperationLogs;

class RecordController extends Controller
{
    //载入游戏规则配置关系
    use GameTypeMap;

    protected $per_page = 15;
    protected $page = 1;
    protected $positionMap = [
        1 => 'east',
        2 => 'south',
        3 => 'west',
        4 => 'north',
    ];          //牌桌方位的映射关系

    public function __construct(Request $request)
    {
        $this->per_page = $request->per_page ?: $this->per_page;
        $this->page = $request->page ?: $this->page;
    }

    //暂未使用
    public function show(AdminRequest $request)
    {
        $api = config('custom.game_api_records');
        $records = GameApiService::request('GET', $api);
        krsort($records);
        return Paginator::paginate($records, $this->per_page, $this->page);
    }

    //战绩信息
    public function search(AdminRequest $request)
    {
        $searchUid = $this->filterSearchRequest($request);

        if ('0' === $searchUid) {
            return Paginator::paginate([]);
        }
        //根据不同类型查询
        switch ($request->get('type',0)){
            //用户id
            case 0:
                $api = config('custom.game_api_records');
                $records = GameApiService::request('POST', $api, [
                    'uid' => $searchUid,
                ]);         //$records为空时分页数据也为空，不会报错
                break;
            case 1:
                //房间id
                $api = config('custom.game_api_record_room');
                $records = GameApiService::request('POST', $api, [
                    'rid' => $searchUid,
                ]);         //$records为空时分页数据也为空，不会报错
                break;
            default:
                throw new CustomException('未知的type');
                break;
        }

        krsort($records);

        foreach ($records as &$record) {
            $record['time'] = $record['ins_time'];

            $recordDetail = json_decode($record['infos']['rec_jstr'], true);
            $record['game_type'] = $this->gameTypes[$recordDetail['room']['options'][1]];    //选项里面key为1的值表示玩法
            $record['room_id'] = $recordDetail['room']['room_id'];
            $record['owner_id'] = isset($recordDetail['room']['owner_uid'])
                ? $recordDetail['room']['owner_uid']    //游戏后端数据更新，兼容新的数据格式
                : $recordDetail['room']['creator']['uid'];

            unset($record['infos']);
        }

        $this->addLog('战绩查询');

        return Paginator::paginate($records, $this->per_page, $this->page);
    }

    //查询指定战绩id的战绩流水
    public function getRecordInfo(AdminRequest $request, $recId, GameOptionsService $gameOptionsService)
    {
        $api = config('custom.game_api_record_info');
        $record = GameApiService::request('POST', $api, [
            'rec_id' => $recId,
        ]);
        $recordDetail = json_decode($record['rec_jstr'], true);
        $recordDetail['players'] = $this->decodeNickname($recordDetail['players']);

        $result['rounds'] = $this->getRounds($recordDetail);                    //战绩流水
        $result['ranking'] = $this->getRanking($recordDetail);                  //总分排行
        $gameType = $recordDetail['room']['options'][1];    //options中key为1表示房间类型
        $result['rules'] = $gameOptionsService->formatOptions($recordDetail['room']['options'], $gameType);   //房间玩法

        OperationLogs::add($request->user()->id, $request->path(), $request->method(),
            '战绩流水查询', $request->header('User-Agent'), json_encode($request->all()));

        return $result;
    }

    //获取总分排行数据
    protected function getRanking($recordDetail)
    {
        $ranking = $recordDetail['players'];
        $roomOwnerId = isset($recordDetail['room']['owner_uid'])
            ? $recordDetail['room']['owner_uid']    //游戏后端数据更新，兼容新的数据格式
            : $recordDetail['room']['creator']['uid'];
        $roundsCount = count($recordDetail['rounds_info']);
        foreach ($ranking as &$player) {
            $player['is_root_owner'] = $player['uid'] === $roomOwnerId ?: false;
            $player['rounds_count'] = $roundsCount;
        }
        //根据总分排倒序
        $ranking = collect($ranking)->sortByDesc('score')->values()->all();
        return $ranking;
    }

    //获取对局的战绩流水
    protected function getRounds($recordDetail)
    {
        $rounds = $recordDetail['rounds_info'];
        $playersMap = $this->getPlayersMap($recordDetail['players']);
        $newRounds = [];
        foreach ($rounds as $round) {
            foreach ($round as &$player) {
                $roundNum = $player['round'];           //第几局
                $player['headimg'] = $playersMap[$player['uid']]['headimg'];
                $player['nickname'] = $playersMap[$player['uid']]['nickname'];
            }
            //给每局数据生成roundx的key, 每局的玩家信息以方位为key
            $newRounds['round' . $roundNum] = array_combine($this->positionMap, $round);
        }

        return $newRounds;
    }

    //返回已玩家id为key的数组
    protected function getPlayersMap($players)
    {
        return array_combine(array_column($players, 'uid'), $players);
    }

    protected function filterSearchRequest($request)
    {
        $this->validate($request, [
            'uid' => 'required|numeric',
        ]);
        return $request->uid;
    }

    protected function decodeNickname($players)
    {
        //一个用户时
        if (isset($players['nickname'])) {
            $players['nickname'] = mb_convert_encoding(base64_decode($players['nickname']), 'UTF-8');;
        } else {
            //多个用户时
            foreach ($players as &$player) {
                //必须要将base64解码之后的字符串转码成utf8格式，不然无法序列化成json字符串
                $player['nickname'] = mb_convert_encoding(base64_decode($player['nickname']), 'UTF-8');
            }
        }

        return $players;
    }
}