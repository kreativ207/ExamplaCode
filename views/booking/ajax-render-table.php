<?php
//BookingTypeOfPaymentEnum::getArrValues()
use app\models\booking\BookingTypeOfPaymentEnum;
use yii\helpers\Html;
?>

<tr>
    <td><input type="text" class="form-control" name="TableAmount[<?= $count_tr;?>]" style="width: 50px"></td>
    <td><?= Html::dropDownList("TableType[$count_tr]", null, BookingTypeOfPaymentEnum::getArrValues()) ?>
    <td><?= date("m/d/Y H:i:s", time()) ?></td>
    <td><?= $name ?></td>
    <td><input type="text" class="form-control" name="TableNotes[<?=$count_tr;?>]" style="width: 90px;"></td>
</tr>