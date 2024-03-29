<?php

namespace Kiri\Rpc;

class LotteryDraw
{


    /**
     * @param array $goods
     * @param string $wight
     * @return mixed
     * @uses $reward = Lucked::luck($data);
     */
    public static function luck(array $goods, string $wight = 'probability'): mixed
    {
        static $class = null;
        if ($class === null) $class = new LotteryDraw();

        if (count($goods) < 1) return null;

        $prob = $alias = [];

        $class->ket($array = $class->init($goods, $wight), $prob, $alias);

        $result = $class->generation($array, $prob, $alias);
        if (!isset($goods[$result])) {
            return null;
        }
        return $goods[$result];
    }


    /**
     * @param array $goods
     * @param string $wight
     * @return array
     */
    protected function init(array $goods, string $wight): array
    {
        $defaultIndex = -1;

        $array = [];
        foreach ($goods as $key => $val) {
            if ($val[$wight] == 0) $defaultIndex = $key;
            $array[] = (float)$val[$wight];
        }

        $total = array_sum($array);
        if ($total > 1) {
            $array = $this->reset($array, $total);
            $total = array_sum($array);
        }
        if (1 - $total !== floatval(0)) {
            if ($defaultIndex == -1) {
                $defaultIndex = count($goods);
            }
            $array[$defaultIndex] = 1 - array_sum($array);
        }

        return $array;
    }


    /**
     * @param array $data
     * @param float $total
     * @return array
     */
    protected function reset(array $data, float $total): array
    {
        $length = $this->getMin($data);
        foreach ($data as $key => $val) {
            $data[$key] = ($val * $length - ($val * $length) / ($total * $length)) / $length;
        }
        return $data;
    }

    /**
     * @param $data
     * @param $prob
     * @param $alias
     * @return mixed
     */
    private function generation($data, $prob, $alias): mixed
    {
        $nums = count($prob) - 1;

        $MAX_P = $this->getMin($data);            // 假设最小的几率是万分之一
        $coin_toss = mt_rand(1, $MAX_P) / $MAX_P; // 抛出硬币

        $col = mt_rand(0, $nums);              // 随机落在一列
        $b_head = $coin_toss > $prob[$col];    // 判断是否落在原色

        return $b_head ? $alias[$col] : $col;
    }

    /**
     * @param $num
     * @return int
     */
    private function getMin($num): int
    {
        $length = $this->getFloatLength($this->mini($num));

        return (int)sprintf('1%0' . $length . 'd', 0);
    }


    /**
     * @param $num
     * @return int|float
     */
    protected function mini($num): int|float
    {
        $def = current($num);
        foreach ($num as $val) {
            if ($val < $def) {
                $def = $val;
            }
        }
        return $def;
    }

    /**
     * @param $float
     * @return int
     */
    private function getFloatLength($float): int
    {
        $ex = explode('.', $float);

        return strlen(end($ex));
    }

    /**
     * @param array $data
     * @param array $prob
     * @param array $alias
     */
    private function ket(array $data, array &$prob, array &$alias): void
    {
        $nums = count($data);
        $small = $large = [];
        for ($i = 0; $i < $nums; ++$i) {
            $data[$i] = $data[$i] * $nums; // 扩大倍数，使每列高度可为1

            /** 分到两个数组，便于组合 */
            if ($data[$i] < 1) {
                $small[] = $i;
            } else {
                $large[] = $i;
            }
        }

        /** 将超过1的色块与原色拼凑成1 */
        while (!empty($small) && !empty($large)) {
            $n_index = array_shift($small);
            $a_index = array_shift($large);

            $prob[$n_index] = $data[$n_index];
            $alias[$n_index] = $a_index;
            // 重新调整大色块
            $data[$a_index] = ($data[$a_index] + $data[$n_index]) - 1;

            if ($data[$a_index] < 1) {
                $small[] = $a_index;
            } else {
                $large[] = $a_index;
            }
        }

        /** 剩下大色块都设为1 */
        while (!empty($large)) {
            $n_index = array_shift($large);
            $prob[$n_index] = 1;
        }

        /** 一般是精度问题才会执行这一步 */
        while (!empty($small)) {
            $n_index = array_shift($small);
            $prob[$n_index] = 1;
        }
    }
}
