<?php
/**
 * Created by PhpStorm.
 * User: rainnogame
 * Date: 09.10.2016
 * Time: 6:55
 */

namespace app\models\calendar;


use app\helpers\TimestampHelper;
use yii\base\Model;

class HelpCalendarPeriods
{
    
    public static function toStringDateArray($periods)
    {
        $newPeriods = [];
        if ($periods) {
            foreach ($periods as $period) {
                $newPeriod = [
                    'from' => $period['from'] ? TimestampHelper::timeToDateString($period['from']) : '',
                    'to'   => $period['to'] ? TimestampHelper::timeToDateString($period['to']) : '',
                ];
                $newPeriods[] = $newPeriod;
            }
        }
        return $newPeriods;
    }
    
    public static function toDaysArray($periods)
    {
        $newPeriods = [];
        if ($periods) {
            foreach ($periods as $period) {
                $newPeriod = $period;
                $newPeriod['from'] = $period['from'] ? TimestampHelper::dateStringToDays($period['from']) : '';
                $newPeriod['to'] = $period['to'] ? TimestampHelper::dateStringToDays($period['to']) : '';
                
                $newPeriods[] = $newPeriod;
            }
        }
        return $newPeriods;
    }
    
    /**
     * @param                  $model
     * @param                  $attribute
     * @param CalendarPeriod[] $periods
     */
    public static function validatePeriodConflicts(Model &$model, $attribute, $periods)
    {

        $periods = CalendarPeriod::sortPeriods($periods);

        $periodsCount = count($periods);
        if ($periodsCount > 1) {
            for ($i = 1; $i < $periodsCount; $i++) {
                $pervPeriod = $periods[$i - 1];
                $currPeriod = $periods[$i];
                if ($pervPeriod->to >= $currPeriod->from) {
                    $from = TimestampHelper::dayToDateString($currPeriod->from);
                    if (!$currPeriod->item_root_id) {
                        $fromItemSting = 'Current';
                    } else {
                        $fromItemSting = PeriodTypeEnum::getValue($currPeriod->item_type) . ' #' . $currPeriod->item_root_id;
                    }
                    
                    if ($pervPeriod->to > $currPeriod->to) {
                        $to = TimestampHelper::dayToDateString($currPeriod->to);
                        if (!$currPeriod->item_root_id) {
                            $toItemString = 'Current';
                        } else {
                            $toItemString = PeriodTypeEnum::getValue($currPeriod->item_type) . ' #' . $currPeriod->item_root_id;
                        }
                    } else {
                        $to = TimestampHelper::dayToDateString($pervPeriod->to);
                        if (!$pervPeriod->item_root_id) {
                            $toItemString = 'Current';
                        } else {
                            $toItemString = PeriodTypeEnum::getValue($pervPeriod->item_type) . ' #' . $pervPeriod->item_root_id;
                        }
                    }

                    $model->addError($attribute, "You have a date conflict between '{$from} - {$to}' ($fromItemSting - $toItemString)");
                }
                
            }
        }
    }
    
}