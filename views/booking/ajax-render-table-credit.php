<?php
//BookingTypeOfPaymentEnum::getArrValues()
use app\models\booking\BookingTypeOfPaymentEnum;
use yii\helpers\Html;
?>

<tr>
    <td style="background: #00ff4e; color: black"><input type="text" class="form-control" name="TableAmountCredit[<?= $count_tr;?>]" style="width: 50px"></td>
    <td style="background: #00ff4e; color: black"><?= Html::dropDownList("TableTypeCredit[$count_tr]", null, BookingTypeOfPaymentEnum::getArrValues()) ?>
    <td style="background: #00ff4e; color: black"><?= date("m/d/Y H:i:s", time()) ?></td>
    <td style="background: #00ff4e; color: black"><?= $name ?></td>
    <td style="background: #00ff4e; color: black"><input type="text" class="form-control" name="TableNotesCredit[<?=$count_tr;?>]" style="width: 90px;"></td>
</tr>