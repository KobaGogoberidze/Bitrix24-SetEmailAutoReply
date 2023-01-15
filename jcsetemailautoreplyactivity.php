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
            "SetReadStatus" => false,
            "Rules" => []
        );

        $this->SetPropertiesTypes(array(
            "Rules" => array(
                "Type" => FieldType::INT,
                "Multiple" => "Y"
            )
        ));
    }
    /**
     * Start the execution of activity
     * 
     * @return CBPActivityExecutionStatus
     */
    public function Execute()
    {
        if (!CModule::IncludeModule("mail")) {
            return CBPActivityExecutionStatus::Closed;
        }

        $rootActivity = $this->GetRootActivity();
        $documentId = $rootActivity->GetDocumentId();

        $arEmployees = CBPHelper::ExtractUsers($this->Employees, $documentId, true);

        if (empty($arEmployees)) {
            $this->WriteToTrackingService(GetMessage("JC_SEAR_EMPLOYEES_NOT_FOUND"), 0, CBPTrackingType::Error);

            return CBPActivityExecutionStatus::Closed;
        }

        $arRuleFields = array(
            "NAME" => GetMessage("JC_SEAR_RULE_NAME"),
            "ACTIVE" => "Y",
            "SORT" => 10,
            "WHEN_MAIL_RECEIVED" => "Y",
            "WHEN_MANUALLY_RUN" => "N",
            "ACTION_READ" => "Y",
            "ACTION_PHP" => str_replace("#AUTO_REPLY_CONTENT#", $this->AutoReplyContent, $this->GetAutoReplyProcessor())
        );
        $arRules = array();

        foreach ($arEmployees as $employeeID) {
            if ($arMailBox = CMailBox::GetList(array(), array("USER_ID" => $employeeID))->Fetch()) {
                $arRules[] = CMailFilter::Add(
                    array_merge(
                        $arRuleFields,
                        array("MAILBOX_ID" => $arMailBox["ID"])
                    )
                );
            } else {
                $this->WriteToTrackingService(str_replace("#EMPLOYEE", $employeeID, GetMessage("JC_SEAR_MAILBOX_NOT_FOUND")), 0, CBPTrackingType::Error);
            }
        }

        $this->arRules = $arRules;

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
    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = '', $popupWindow = null, $siteId = '')
    {
        if (!CModule::IncludeModule("mail")) {
            return '';
        }

        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $arWorkflowTemplate,
            "workflowParameters" => $arWorkflowParameters,
            "workflowVariables" => $arWorkflowVariables,
            "currentValues" => $arCurrentValues,
            "formName" => $formName,
            "siteId" => $siteId
        ));

        $dialog->setMap(static::getPropertiesDialogMap());

        return $dialog;
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
     * @return bool
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        if (!CModule::IncludeModule("mail")) {
            return "";
        }

        $documentService = CBPRuntime::GetRuntime(true)->getDocumentService();
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $arWorkflowTemplate,
            "workflowParameters" => $arWorkflowParameters,
            "workflowVariables" => $arWorkflowVariables,
            "currentValues" => $arCurrentValues,
        ));

        $arProperties = [];
        foreach (static::getPropertiesDialogMap() as $fieldID => $arFieldProperties) {
            $field = $documentService->getFieldTypeObject($dialog->getDocumentType(), $arFieldProperties);
            if (!$field) {
                continue;
            }

            $arProperties[$fieldID] = $field->extractValue(
                ['Field' => $arFieldProperties['FieldName']],
                $arCurrentValues,
                $arErrors
            );
        }

        $arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if (count($arErrors) > 0) {
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $currentActivity["Properties"] = $arProperties;

        return true;
    }

    /**
     * Validate user provided properties
     * 
     * @param array $arTestProperties
     * @param CBPWorkflowTemplateUser $user
     * @return array
     */

    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();
        foreach (static::getPropertiesDialogMap() as $fieldID => $arFieldProperties) {
            if (isset($arFieldProperties["Required"]) && $arFieldProperties["Required"] && empty($arTestProperties[$fieldID])) {
                $arErrors[] = array(
                    "code" => "emptyText",
                    "parameter" => $fieldID,
                    "message" => str_replace("#FIELD_NAME#", $arFieldProperties["Name"], GetMessage("JC_SEAR_FIELD_NOT_SPECIFIED")),
                );
            }
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    /**
     * Get autoreply rule processor
     * 
     * @return string
     */

    private static function GetAutoReplyProcessor()
    {
        return "
            \$from = CMailUtil::ExtractMailAddress(\$arMessageFields[\"FIELD_TO\"]);
            \$to = CMailUtil::ExtractMailAddress(\$arMessageFields[\"FIELD_FROM\"]);
            \$autoReplyContent = \"#AUTO_REPLY_CONTENT#\";

            if (\$from != \$to) {
                \$context = new Bitrix\Main\Mail\Context();
                \$context->setCategory(Bitrix\Main\Mail\Context::CAT_EXTERNAL)
                    ->setPriority(Bitrix\Main\Mail\Context::PRIORITY_LOW);

                \$arMailParams = array(
                    \"CHARSET\" => SITE_CHARSET,
                    \"CONTENT_TYPE\" => \"html\",
                    \"TO\" => \$to,
                    \"BODY\" => \$autoReplyContent,
                    \"HEADER\" => array(
                        \"From\" => \$from,
                        \"Reply-To\" => \$to,
                        \"Message-Id\" => \$messageId,
                    ),
                    \"CONTEXT\" => \$context,
                );

                Bitrix\Main\Mail\Mail::send(\$arMailParams);
            }
        ";
    }

    /**
     * User provided properties
     * 
     * @return array
     */

    private static function getPropertiesDialogMap()
    {
        return array(
            "Employees" => array(
                "Name" => GetMessage("JC_SEAR_EMPLOYEES_FIELD_TITLE"),
                "FieldName" => "Employees",
                "Type" => FieldType::USER,
                "Multiple" => "Y",
                "Required" => true
            ),
            "AutoReplyContent" => array(
                "Name" => GetMessage("JC_SEAR_AUTO_REPLY_CONTENT_FIELD_TITLE"),
                "FieldName" => "AutoReplyContent",
                "Type" => FieldType::TEXT,
                "Required" => true
            ),
            "SetReadStatus" => array(
                "Name" => GetMessage("JC_SEAR_SET_READ_STATUS_FIELD_TITLE"),
                "FieldName" => "SetReadStatus",
                "Type" => FieldType::BOOL,
                "Required" => true,
                "Default" => "N",
            )
        );
    }
}
