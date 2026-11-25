<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

namespace Hesk\Hook;

abstract class AbstractHook
{
    function onPermissions($permissions) {
        return $permissions;
    }
    function onEmailTemplates($emails) {
        return $emails;
    }
    function onPermissionFailure($permission, $user) {
    }
    function onAuthenticationEvent($authenticationEvent, $user) {
    }
    function onPersonalInformationViewed($subject, $user) {
    }
    //region Tickets
    function onTicketCreated($ticket, $user) {
    }
    function onTicketViewed($ticket, $user) {
    }
    function onTicketEditFormViewed($ticket, $user) {
    }
    function onTicketUpdated($newValues, $user) {
    }
    function onTicketsDeleted($tickets, $user) {
    }
    function onTicketsPrinted($tickets, $user) {
    }
    function onTicketAttachmentCreated($attachment, $user) {
    }
    function onTicketAttachmentDeleted($id, $user) {
    }
    function onTicketsLinked($tickets, $user) {
    }
    function onTicketsUnlinked($tickets, $user) {
    }
    function onTicketCollaboratorAdded($ticketId, $collaborator, $user) {
    }
    function onTicketCollaboratorRemoved($ticketId, $collaborator, $user) {
    }
    function onTicketAnonymized($ticketId, $user) {
    }
    function onTicketsMerged($mainTicket, $mergedTickets, $user) {
    }
    function onTicketEmailNotificationResent($ticketInfo, $user) {
    }
    //endregion
    //region Ticket Replies
    function onTicketReplyCreated($reply, $user) {
    }
    function onTicketReplyEditFormViewed($reply, $user) {
    }
    function onTicketReplyUpdated($newValues, $user) {
    }
    function onTicketReplyDeleted($reply, $user) {
    }
    function onTicketReplyAttachmentCreated($attachment, $user) {
    }
    function onTicketReplyAttachmentDeleted($id, $user) {
    }
    //endregion
    //region Ticket Notes
    function onTicketNoteCreated($note, $user) {
    }
    function onTicketNoteUpdated($newValues, $user) {
    }
    function onTicketNoteDeleted($note, $user) {
    }
    //endregion
    //region File Downloads
    function onAttachmentDownloaded($attachment, $user) {
    }
    function onTemporaryAttachmentCreated($attachment, $user) {
    }
    function onTemporaryAttachmentDeleted($attachment, $user) {
    }
    //endregion
    //region Email Sent
    function onEmailSent($email, $user) {
    }
    function onEmailImport($action, $data, $user) {
    }
    //endregion
    //region Ticket Templates
    function onTicketTemplatesViewed($user) {
    }
    function onTicketTemplateCreated($ticketTemplate, $user) {
    }
    function onTicketTemplateUpdated($newValues, $user) {
    }
    function onTicketTemplateDeleted($ticketTemplate, $user) {
    }
    //endregion
    //region Canned Responses
    function onCannedResponsesViewed($user) {
    }
    function onCannedResponseCreated($cannedResponse, $user) {
    }
    function onCannedResponseUpdated($newValues, $user) {
    }
    function onCannedResponseDeleted($cannedResponse, $user) {
    }
    //endregion
    //region KB Categories
    function onKnowledgebaseCategoryViewed($knowledgebaseCategory, $user) {
    }
    function onKnowledgebaseCategoryCreated($knowledgebaseCategory, $user) {
    }
    function onKnowledgebaseCategoryUpdated($newValues, $user) {
    }
    function onKnowledgebaseCategoryDeleted($knowledgebaseCategory, $user) {
    }
    //endregion
    //region KB Articles
    function onKnowledgebaseArticleViewed($knowledgebaseArticle, $user) {
    }
    function onKnowledgebaseArticleCreated($knowledgebaseArticle, $user) {
    }
    function onKnowledgebaseArticleUpdated($newValues, $user) {
    }
    function onKnowledgebaseArticleDeleted($knowledgebaseArticle, $user) {
    }
    function onKnowledgebaseArticleAttachmentCreated($attachment, $user) {
    }
    function onKnowledgebaseArticleAttachmentDeleted($id, $user) {
    }
    //endregion
    //region KB Search
    function onKnowledgebaseSearchExecuted($search, $user) {
    }
    //endregion
    //region Ticket Categories
    function onTicketCategoriesViewed($user) {
    }
    function onTicketCategoryCreated($ticketCategory, $user) {
    }
    function onTicketCategoryUpdated($newValues, $user) {
    }
    function onTicketCategoryDeleted($ticketCategory, $user) {
    }
    //endregion
    //region Search Queries
    function onSearchQueryExecuted($searchQuery, $user) {
    }
    //endregion
    //region Customers
    function onCustomersViewed($user) {
    }
    function onCustomerSearchExecuted($customerIds, $user) {
    }
    function onCustomerCreated($customer, $user) {
    }
    function onCustomerUpdated($newValues, $user) {
    }
    function onCustomerDeleted($customer, $user) {
    }
    function onCustomersMerged($affectedRecords, $user) {
    }
    function onCustomerMfaCodesReset($customer, $user) {
    }
    function onCustomerMfaCodesGenerated($customer, $user) {
    }
    function onCustomerMfaCodesDeleted($customer, $user) {
    }
    //endregion
    //region Private Messages
    function onPrivateMessageListViewed($messages, $user) {
    }
    function onPrivateMessageViewed($message, $user) {
    }
    function onPrivateMessageCreated($message, $user) {
    }
    function onPrivateMessageStatusChanged($message, $user) {
    }
    function onPrivateMessageDeleted($message, $user) {
    }
    //endregion
    //region Team Members
    function onTeamMembersViewed($user) {
    }
    function onTeamMemberCreated($teamMember, $user) {
    }
    function onTeamMemberUpdated($newValues, $user) {
    }
    function onTeamMemberDeleted($teamMember, $user) {
    }
    function onTeamMemberMfaCodesReset($teamMember, $user) {
    }
    function onTeamMemberMfaCodesGenerated($teamMember, $user) {
    }
    function onTeamMemberMfaCodesDeleted($teamMember, $user) {
    }
    //endregion
    //region Permission Groups
    function onPermissionGroupsViewed($user) {
    }
    function onPermissionGroupCreated($permissionGroup, $user) {
    }
    function onPermissionGroupUpdated($newValues, $user) {
    }
    function onPermissionGroupDeleted($permissionGroup, $user) {
    }
    //endregion
    //region Banned Emails
    function onBannedEmailsRead($user) {
    }
    function onBannedEmailCreated($bannedEmail, $user) {
    }
    function onBannedEmailDeleted($bannedEmail, $user) {
    }
    //endregion
    //region Muted Emails
    function onMutedEmailsRead($user) {
    }
    function onMutedEmailCreated($mutedEmail, $user) {
    }
    function onMutedEmailDeleted($mutedEmail, $user) {
    }
    //endregion
    //region Banned IPs
    function onBannedIpsRead($user) {
    }
    function onBannedIpCreated($bannedIp, $user) {
    }
    function onBannedIpDeleted($bannedIp, $user) {
    }
    //endregion
    //region Service Messages
    function onServiceMessagesViewed($user) {
    }
    function onServiceMessageCreated($serviceMessage, $user) {
    }
    function onServiceMessageUpdated($newValues, $user) {
    }
    function onServiceMessageDeleted($serviceMessage, $user) {
    }
    //endregion
    //region Email Templates
    function onEmailTemplatesViewed($user) {
    }
    function onEmailTemplateUpdated($newValues, $user) {
    }
    //endregion
    //region Custom Fields
    function onCustomFieldsViewed($user) {
    }
    function onCustomFieldCreated($customField, $user) {
    }
    function onCustomFieldUpdated($newValues, $user) {
    }
    function onCustomFieldDeleted($customField, $user) {
    }
    //endregion
    //region Ticket Statuses
    function onTicketStatusesViewed($user) {
    }
    function onTicketStatusCreated($ticketStatus, $user) {
    }
    function onTicketStatusUpdated($newValues, $user) {
    }
    function onTicketStatusDeleted($ticketStatus, $user) {
    }
    //endregion
    //region Ticket Priorities
    function onTicketPrioritiesViewed($user) {
    }
    function onTicketPriorityCreated($ticketPriority, $user) {
    }
    function onTicketPriorityUpdated($newValues, $user) {
    }
    function onTicketPriorityDeleted($ticketPriority, $user) {
    }
    //endregion
    //region OAuth Providers
    function onOauthProvidersViewed($user) {
    }
    function onOauthProviderCreated($oauthProvider, $user) {
    }
    function onOauthProviderUpdated($newValues, $user) {
    }
    function onOauthProviderDeleted($oauthProvider, $user) {
    }
    //endregion
    //region Settings
    function onSettingsViewed($settingsPage, $user) {
    }
    function onSettingsUpdated($newValues, $user) {
    }
    //endregion
    //region Audit Log
    function onAuditLogViewed($view, $user) {
    }
    function onAuditLogExportAction($details, $user) {
    }
    //endregion
    //region Reports / Export Tickets
    function onReportExecuted($filter, $user) {
    }
    function onTicketExported($filter, $user) {
    }
    function onTicketExportDownloaded($exportId, $user) {
    }
    function onTicketExportDeleted($fileName, $user) {
    }
    //endregion
    //region Module - Escalate
    function onEscalationRulesViewed($user) {
    }
    function onEscalationRuleCreated($escalationRule, $user) {
    }
    function onEscalationRuleUpdated($newValues, $user) {
    }
    function onEscalationRuleDeleted($escalationRule, $user) {
    }
    function onEscalationRulesBackupExported($details, $user) {
    }
    function onEscalationRulesBackupRestored($details, $user) {
    }
    //endregion
    //region Module - Recurring Tickets
    function onRecurringTicketsViewed($user) {
    }
    function onRecurringTicketCreated($recurringTicket, $user) {
    }
    function onRecurringTicketUpdated($newValues, $user) {
    }
    function onRecurringTicketDeleted($recurringTicket, $user) {
    }
    function onRecurringTicketsBackupExported($details, $user) {
    }
    function onRecurringTicketsBackupRestored($details, $user) {
    }
    //endregion
    //region Module - Satisfaction
    function onSatisfactionModuleViewed($user) {
    }
    function onSatisfactionModuleUpdated($settings, $user) {
    }
    function onSatisfactionModuleOptOutViewed($user) {
    }
    function onSatisfactionModuleOptOutCreated($email, $user) {
    }
    function onSatisfactionModuleOptOutDeleted($id, $user) {
    }
    //endregion
    //region Module - Statistics
    function onStatisticsViewed($filter, $user) {
    }
    //endregion
}
