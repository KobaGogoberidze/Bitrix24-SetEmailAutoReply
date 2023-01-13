<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Bizproc\FieldType;

class CBPJCSetEmailAutoReplyActivity extends CBPActivity
{
    /**
     * Initialize activity
     * 
     * @param string $name
     */
    public function __construct($name)
    {

        $this->arProperties = array(
            "Title" => "",
            "Employees" => "",
            "AutoReplyContent" => "",
            "SetReadStatus" => false
        );

        $this->SetPropertiesTypes(array(
            "Employees" => array(
                "Type" => FieldType::USER
            ),
            "AutoReplyContent" => array(
                "Type" => FieldType::STRING
            ),
            "SetReadStatus" => array(
                "Type" => FieldType::BOOL
            ),
        ));
    }
    /**
     * Start the execution of activity
     * 
     * @return CBPActivityExecutionStatus
     */
    public function Execute()
    {
        $rootActivity = $this->GetRootActivity();
        $documentId = $rootActivity->GetDocumentId();

        $arEmployees = CBPHelper::ExtractUsers($this->Employees, $documentId, true);

        if (empty($arEmployees)) {
            $this->WriteToTrackingService(str_replace("#EMPLOYEE", $employeeID, GetMessage("JC_WL2F_FILE_PATH_EMPTY")), 0, CBPTrackingType::Error);

            return CBPActivityExecutionStatus::Closed;
        }

        $arRuleFields = array(
            "NAME" => GetMessage("JC_WL2F_FILE_PATH_EMPTY"),
            "ACTIVE" => "Y",
            "SORT" => 10,
            "WHEN_MAIL_RECEIVED" => "Y",
            "WHEN_MANUALLY_RUN" => "N",
            "ACTION_READ" => "Y"
        );

        foreach ($arEmployees as $employeeID) {
            if ($arMailBox = CMailBox::GetList(array(), array("USER_ID" => $employeeID))) {
                CMailFilter::Add(
                    array_merge(
                        $arRuleFields,
                        array(
                            "MAILBOX_ID" => $arMailBox["ID"],
                            "ACTION_PHP" => str_replace("#AUTO_REPLY_CONTENT#", $this->AutoReplyContent, $this->GetAutoReplyProcessor())
                        )
                    )
                );
            } else {
                $this->WriteToTrackingService(str_replace("#EMPLOYEE", $employeeID, GetMessage("JC_WL2F_FILE_PATH_EMPTY")), 0, CBPTrackingType::Error);
            }
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Generate setting form
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array $arWorkflowTemplate
     * @param array $arWorkflowParameters
     * @param array $arWorkflowVariables
     * @param array $arCurrentValues
     * @param string $formName
     * @return string
     */
    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
    {
    }

    /**
     * Process form submition
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array &$arWorkflowTemplate
     * @param array &$arWorkflowParameters
     * @param array &$arWorkflowVariables
     * @param array &$arCurrentValues
     * @param array &$arErrors
     * @return boolean
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
    }

    /**
     * Validate properties
     * 
     * @param array $arTestProperties
     * @param CBPWorkflowTemplateUser $user
     * @return array
     */

    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
    }

    /**
     * @return string
     */

    public static function GetAutoReplyProcessor()
    {
        return "
            \$from = CMailUtil::ExtractMailAddress(\$arMessageFields[\"FIELD_FROM\"]);
            \$to = CMailUtil::ExtractMailAddress(\$arMessageFields[\"FIELD_FROM\"]);

            \$autoReplyContent = \"#AUTO_REPLY_CONTENT#\";

            if (\$from != \$to) {
                \$arMailParams = array();

                Mail::send(\$arMailParams);
            }
        ";
    }
}
