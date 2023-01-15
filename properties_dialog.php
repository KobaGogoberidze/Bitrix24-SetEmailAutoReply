<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>
<tr>
    <td align="right" width="40%" valign="top"><span class="adm-required-field"><?= GetMessage("JC_SEAR_EMPLOYEES_FIELD_TITLE") ?>:</span></td>
    <td width="60%">
        <?= CBPDocument::ShowParameterField("user", 'Employees', $arCurrentValues['Employees'], array('rows' => 1, 'cols' => 50)) ?>
    </td>
</tr>
<tr>
    <td align="right" width="40%" valign="top"><span class="adm-required-field"><?= GetMessage("JC_SEAR_AUTO_REPLY_CONTENT_FIELD_TITLE") ?>:</span></td>
    <td width="60%">
        <?= CBPDocument::ShowParameterField("text", 'AutoReplyContent', $arCurrentValues['AutoReplyContent'], array('rows' => 10, 'cols' => 50)) ?>
    </td>
</tr>
<tr>
    <td align="right" width="40%" valign="top"><span class="adm-required-field"><?= GetMessage("JC_SEAR_SET_READ_STATUS_FIELD_TITLE") ?>:</span></td>
    <td width="60%">
        <?= CBPDocument::ShowParameterField("bool", 'SetReadStatus', $arCurrentValues['SetReadStatus']) ?>
    </td>
</tr>