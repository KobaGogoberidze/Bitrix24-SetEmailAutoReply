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
            "Rules" => ""
        );

        $this->SetPropertiesTypes(array(
            "Employees" => array(
                "Type" => FieldType::USER,
                "Multiple" => "Y"
            ),
            "AutoReplyContent" => array(
                "Type" => FieldType::STRING
            ),
            "SetReadStatus" => array(
                "Type" => FieldType::BOOL
            ),
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
            "ACTION_READ" => "Y",
            "ACTION_PHP" => str_replace("#AUTO_REPLY_CONTENT#", $this->AutoReplyContent, $this->GetAutoReplyProcessor())
        );

        $arRules = array();

        foreach ($arEmployees as $employeeID) {
            if ($arMailBox = CMailBox::GetList(array(), array("USER_ID" => $employeeID))) {
                $arRules[] = CMailFilter::Add(
                    array_merge(
                        $arRuleFields,
                        array("MAILBOX_ID" => $arMailBox["ID"])
                    )
                );
            } else {
                $this->WriteToTrackingService(str_replace("#EMPLOYEE", $employeeID, GetMessage("JC_WL2F_FILE_PATH_EMPTY")), 0, CBPTrackingType::Error);
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
    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
    {
        $runtime = CBPRuntime::GetRuntime();

        if (!is_array($arCurrentValues)) {
            $arCurrentValues = array(
                "Employees" => "",
                "AutoReplyContent" => "",
                "SetReadStatus" => false,
            );

            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (is_array($arCurrentActivity["Properties"])) {
                $arCurrentValues["Employees"] = CBPHelper::UsersArrayToString($arCurrentActivity["Properties"]["Employees"], $arWorkflowTemplate, $documentType);
                $arCurrentValues["AutoReplyContent"] = $arCurrentActivity["Properties"]["AutoReplyContent"];
                $arCurrentValues["SetReadStatus"] = $arCurrentActivity["Properties"]["SetReadStatus"];
            }
        }

        return $runtime->ExecuteResourceFile(
            __FILE__,
            "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "formName" => $formName,
            )
        );
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
        $arErrors = array();

        $arProperties = array(
            "Employees" => CBPHelper::UsersArrayToString($arCurrentValues["Employees"], $arWorkflowTemplate, $documentType),
            "AutoReplyContent" => $arCurrentValues["AutoReplyContent"],
            "SetReadStatus" => $arCurrentValues["SetReadStatus"],
        );

        $arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
        if (count($arErrors) > 0) {
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $currentActivity["Properties"] = $arProperties;

        return true;
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
        $arErrors = array();

        if (empty($arTestProperties["Employees"])) {
            $arErrors[] = array(
                "code" => "emptyText",
                "parameter" => "Employees",
                "message" => GetMessage("JC_WL2F_CONTENT_EMPTY"),
            );
        }
        if (empty($arTestProperties["AutoReplyContent"])) {
            $arErrors[] = array(
                "code" => "emptyText",
                "parameter" => "AutoReplyContent",
                "message" => GetMessage("JC_WL2F_FILE_PATH_EMPTY"),
            );
        }
        if (empty($arTestProperties["SetReadStatus"])) {
            $arErrors[] = array(
                "code" => "emptyText",
                "parameter" => "SetReadStatus",
                "message" => GetMessage("JC_WL2F_FILE_PATH_EMPTY"),
            );
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
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
