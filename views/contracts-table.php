<?php if($arrays && count($arrays) >= 1): ?>
    <?php foreach ($arrays as $id => $arr): ?>
        <table id="default-<?=$id;?>" style="padding: 25px">
            <tr>
                <td colspan=2><b>Информация про клиента</b></td>
            </tr>
            <tr>
                <td>название клиента</td>
                <td id="name_customer"><?= $arr['name_customer'] ? $arr['name_customer'] : ""; ?></td>
            </tr>
            <tr>
                <td>компания</td>
                <td id="company"><?= $arr['company'] ? $arr['company'] : ""; ?></td>
            </tr>
            <tr>
                <td colspan=2><b>информация про договор</b></td>
            </tr>
            <tr>
                <td>номер договора</td>
                <td id="number_contract"><?= $arr['staff_number'] ? $arr['staff_number'] : ""; ?></td>
            </tr>
            <tr>
                <td>дата подписания</td>
                <td id="date_sign"><?= $arr['date_sign'] ? $arr['date_sign'] : ""; ?></td>
            </tr>
            <tr>
                <td colspan=2><b>информация про сервисы</b></td>
            </tr>
                <?php if(!isset($arr['statusAll'])): ?>
            <tr>
                    <td id="services_name"><?= $arr['title_service'] ? $arr['title_service'] : ""; ?></td>
                    <td id="services_status"><?= $arr['status'] ? $arr['status'] : ""; ?></td>
            </tr>
                <?php else: ?>
                    <?php foreach ($arr['statusAll'] as $status): ?>
                        <tr>
                            <td id="services_name"><?= $status['title_service'] ? $status['title_service'] : ""; ?></td>
                            <td id="services_status"><?= $status['status'] ? $status['status'] : ""; ?></td>
                        </tr>
                    <?php endforeach; ?>
            <?php endif; ?>

        </table>
        <?php endforeach; ?>

<?php endif; ?>