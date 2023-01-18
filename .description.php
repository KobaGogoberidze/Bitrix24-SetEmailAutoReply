<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = array(
    "NAME" => GetMessage("JC_SEAR_NAME"),
    "DESCRIPTION" => GetMessage("JC_SEAR_DESCRIPTION"),
    "TYPE" => "activity",
    "CLASS" => "JCSetEmailAutoReplyActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",
    )
);
