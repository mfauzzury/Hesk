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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

function hesk_hook_permissions($permissions) {
    return hesk_hooks_apply_filter($permissions, function($hook, $current_permissions) {
        return $hook->onPermissions($current_permissions);
    });
}

function hesk_hook_email_templates($emails) {
    return hesk_hooks_apply_filter($emails, function($hook, $current_emails) {
        return $hook->onEmailTemplates($current_emails);
    });
}

function hesk_hook_permission_failure($permission, $target = array()) {
    hesk_hooks_call_hook(function($hook) use ($permission, $target) {
        $user = hesk_hooks_build_user_info($permission);
        $user['target'] = $target;
        $hook->onPermissionFailure($permission, $user);
    });
}

function hesk_hooks_redirect_permission_failure($success, $target = null, $target_type = '') {
    if (!is_string($success) ||
        (strncmp($success, 'feature|', 8) !== 0 && strncmp($success, 'category|', 9) !== 0)) {
        return false;
    }

    $target_context = array();
    if ($target_type !== '') {
        $target_context['type'] = $target_type;
    }

    if (is_array($target)) {
        foreach (array('id', 'att_id', 'customer_id', 'user_id') as $id_key) {
            if (isset($target[$id_key]) && !is_array($target[$id_key])) {
                $target_context['id'] = $target[$id_key];
                break;
            }
        }
        if (isset($target['trackid']) && !is_array($target['trackid'])) {
            $target_context['secondary_id'] = $target['trackid'];
        }
        foreach (array('replyto', 'ticket_id') as $parent_key) {
            if (isset($target[$parent_key]) && !is_array($target[$parent_key])) {
                $target_context['parent_id'] = $target[$parent_key];
                break;
            }
        }

        $target_ids = array();
        if (isset($target['ids']) && is_array($target['ids'])) {
            $target_ids = $target['ids'];
        } elseif (count($target) > 0 && array_keys($target) === range(0, count($target) - 1)) {
            $target_ids = $target;
        }
        if ($target_ids) {
            $target_context['ids'] = array();
            foreach ($target_ids as $target_id) {
                if (is_scalar($target_id) && $target_id !== '') {
                    $target_context['ids'][] = $target_id;
                }
            }
            if (!$target_context['ids']) {
                unset($target_context['ids']);
            }
        }
    } elseif (is_scalar($target) && $target !== '') {
        $target_context['id'] = $target;
    }

    hesk_hook_permission_failure($success, $target_context);
    return true;
}
function hesk_hook_authentication_event($authenticationEvent, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($authenticationEvent, $success, $actor) {
        $hook->onAuthenticationEvent($authenticationEvent, hesk_hooks_build_user_info($success, $actor));
    });
}

function hesk_hook_personal_information_viewed($subject, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($subject, $success, $actor) {
        $hook->onPersonalInformationViewed($subject, hesk_hooks_build_user_info($success, $actor));
    });
}

function hesk_hooks_get_changed_fields($oldValues, $newValues, $ignoredFields = array(), $fieldAliases = array(), $dbEscapedFields = array()) {
    if (!is_array($oldValues) || !is_array($newValues)) {
        return array();
    }

    $changes = array();
    foreach ($newValues as $field => $newValue) {
        if (in_array($field, $ignoredFields, true)) {
            continue;
        }

        // Compare text in the same form hesk_dbEscape() stores it.
        if (in_array($field, $dbEscapedFields, true) && is_string($newValue)) {
            $newValue = str_replace('`', '&#96;', stripslashes($newValue));
        }

        $oldField = isset($fieldAliases[$field]) ? $fieldAliases[$field] : $field;
        if (!array_key_exists($oldField, $oldValues) || !hesk_hooks_values_are_equal($oldValues[$oldField], $newValue)) {
            $changes[] = $oldField;
        }
    }

    return array_values(array_unique($changes));
}

function hesk_hooks_values_are_equal($oldValue, $newValue) {
    return hesk_hooks_normalize_comparison_value($oldValue) === hesk_hooks_normalize_comparison_value($newValue);
}

function hesk_hooks_normalize_comparison_value($value) {
    if (is_array($value)) {
        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized[$key] = hesk_hooks_normalize_comparison_value($item);
        }

        $keys = array_keys($normalized);
        if ($keys === range(0, count($normalized) - 1)) {
            sort($normalized, SORT_STRING);
        } else {
            ksort($normalized, SORT_STRING);
        }

        return serialize($normalized);
    }

    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string) $value;
}
// Only these identity fields may be retained in account lifecycle events.
function hesk_hooks_get_identity($values) {
    $identity = array();
    if (!is_array($values)) {
        return $identity;
    }
    if (isset($values['user']) && !isset($values['username'])) {
        $values['username'] = $values['user'];
    }
    foreach (array('name', 'username', 'email') as $field) {
        if (isset($values[$field]) && is_string($values[$field]) && strlen($values[$field]) <= 2000 && preg_match('//u', $values[$field])) {
            $identity[$field] = $values[$field];
        }
    }
    return $identity;
}

// Keep access controls separate from passwords, tokens and other secret settings.
function hesk_hooks_get_security_values($values) {
    if (!is_array($values)) {
        return array();
    }
    if (array_key_exists('heskprivileges', $values) && !array_key_exists('features', $values)) {
        $values['features'] = $values['heskprivileges'];
    }
    $integer_fields = array(
        'isadmin', 'active', 'verified', 'mfa_enrollment',
        'autologin', 'customer_autologin', 'customer_accounts', 'customer_accounts_required',
        'customer_accounts_customer_self_register',
        'customer_accounts_admin_approvals', 'customer_accounts_allow_email_changes',
        'customer_accounts_verify_email_cooldown', 'secimg_use', 'recaptcha_use', 'question_use',
        'attempt_limit', 'attempt_banmin', 'reset_pass', 'require_email', 'email_view_ticket',
        'require_mfa', 'require_mfa_customers', 'extend_admin', 'extend_customer',
        'force_ssl', 'pass_staff_expire', 'pass_customers_expire',
        'pass_staff_expire_value', 'pass_customers_expire_value', 'pass_minimum_length',
        'pass_lowercase_letters', 'pass_uppercase_letters', 'pass_numbers',
        'pass_special_symbols', 'pass_history_limit', 'pass_check_common', 'debug_mode'
    );
    $safe = array();
    foreach ($values as $field => $value) {
        if (in_array($field, $integer_fields, true)) {
            if ($value === null || is_bool($value) || (is_scalar($value) && preg_match('/^-?[0-9]{1,10}$/', (string) $value))) {
                $safe[$field] = $value === null ? null : intval($value);
            }
        } elseif (in_array($field, array('categories', 'permission_groups', 'users', 'features'), true)) {
            if ($value === null) {
                $safe[$field] = null;
                continue;
            }
            if (is_string($value)) {
                $value = $value === '' ? array() : explode(',', $value);
            }
            if (!is_array($value)) {
                continue;
            }
            $items = array();
            foreach ($value as $item) {
                if ($field === 'features') {
                    if (is_string($item) && preg_match('/^[A-Za-z0-9_]{1,100}$/', $item)) {
                        $items[] = $item;
                    }
                } elseif (is_scalar($item) && preg_match('/^[0-9]{1,10}$/', (string) $item) && intval($item) > 0) {
                    $items[] = intval($item);
                }
            }
            $items = array_values(array_unique($items));
            sort($items, $field === 'features' ? SORT_STRING : SORT_NUMERIC);
            $safe[$field] = $items;
        } elseif (in_array($field, array('pass_staff_expire_unit', 'pass_customers_expire_unit'), true)) {
            if ($value === null || in_array($value, array('days', 'months'), true)) {
                $safe[$field] = $value;
            }
        } elseif ($field === 'samesite') {
            if ($value === null || in_array($value, array('Strict', 'Lax', 'None'), true)) {
                $safe[$field] = $value;
            }
        } elseif ($field === 'elevator_duration') {
            if ($value === null || (is_string($value) && preg_match('/^[0-9]{1,6}[MHD]$/', $value))) {
                $safe[$field] = $value;
            }
        } elseif ($field === 'pass_expires_at') {
            if ($value === null || (is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value))) {
                $safe[$field] = $value;
            }
        }
    }
    return $safe;
}

function hesk_hooks_get_security_changes($oldValues, $newValues) {
    $oldValues = hesk_hooks_get_security_values($oldValues);
    $newValues = hesk_hooks_get_security_values($newValues);
    $changes = array();
    foreach ($newValues as $field => $newValue) {
        $oldValue = array_key_exists($field, $oldValues) ? $oldValues[$field] : null;
        if ($oldValue !== $newValue) {
            $changes[$field] = array('old' => $oldValue, 'new' => $newValue);
        }
    }
    return $changes;
}

//region Tickets
function hesk_hook_ticket_created($ticket, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $success, $actor) {
        $hook->onTicketCreated($ticket, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_ticket_viewed($ticket, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $success, $actor) {
        $hook->onTicketViewed($ticket, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_ticket_edit_form_viewed($ticket, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $success) {
        $hook->onTicketEditFormViewed($ticket, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_updated($changes, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success, $actor) {
        $hook->onTicketUpdated($changes, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_tickets_deleted($tickets, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $tickets, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($tickets, $success) {
        $hook->onTicketsDeleted($tickets, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_tickets_printed($tickets, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $tickets, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($tickets, $success, $actor) {
        $hook->onTicketsPrinted($tickets, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_ticket_attachment_created($attachment, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'ticket_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success) {
        $hook->onTicketAttachmentCreated($attachment, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_attachment_deleted($ids, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ids, 'ticket_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ids, $success) {
        $hook->onTicketAttachmentDeleted($ids, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_tickets_linked($tickets, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $tickets, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($tickets, $success) {
        $hook->onTicketsLinked($tickets, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_tickets_unlinked($tickets, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $tickets, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($tickets, $success) {
        $hook->onTicketsUnlinked($tickets, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_collaborator_added($ticket, $collaborator, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $collaborator, $success) {
        $hook->onTicketCollaboratorAdded($ticket, $collaborator, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_collaborator_removed($ticket, $collaborator, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $collaborator, $success) {
        $hook->onTicketCollaboratorRemoved($ticket, $collaborator, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_anonymized($ticket, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticket, $success) {
        $hook->onTicketAnonymized($ticket, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_tickets_merged($main_ticket, $merged_tickets, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $main_ticket, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($main_ticket, $merged_tickets, $success) {
        $hook->onTicketsMerged($main_ticket, $merged_tickets, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Replies
function hesk_hook_ticket_reply_created($ticketReply, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketReply, 'ticket_reply')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketReply, $success, $actor) {
        $hook->onTicketReplyCreated($ticketReply, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_ticket_reply_edit_form_viewed($ticketReply, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketReply, 'ticket_reply')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketReply, $success) {
        $hook->onTicketReplyEditFormViewed($ticketReply, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_reply_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'ticket_reply')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketReplyUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_reply_deleted($ticketReply, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketReply, 'ticket_reply')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketReply, $success) {
        $hook->onTicketReplyDeleted($ticketReply, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_reply_attachment_created($attachment, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'ticket_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success, $actor) {
        $hook->onTicketReplyAttachmentCreated($attachment, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_ticket_reply_attachment_deleted($id, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $id, 'ticket_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($id, $success) {
        $hook->onTicketReplyAttachmentDeleted($id, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Notes
function hesk_hook_ticket_note_created($ticketNote, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketNote, 'ticket_note')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketNote, $success) {
        $hook->onTicketNoteCreated($ticketNote, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_note_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'ticket_note')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketNoteUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_note_deleted($ticketNote, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketNote, 'ticket_note')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketNote, $success) {
        $hook->onTicketNoteDeleted($ticketNote, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region File Downloads
function hesk_hook_attachment_downloaded($attachment, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success, $actor) {
        $hook->onAttachmentDownloaded($attachment, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_temporary_attachment_created($attachment, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'temporary_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success, $actor) {
        $hook->onTemporaryAttachmentCreated($attachment, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_temporary_attachment_deleted($attachment, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'temporary_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success, $actor) {
        $hook->onTemporaryAttachmentDeleted($attachment, hesk_hooks_build_user_info($success, $actor));
    });
}
//endregion
//region Email Sent
function hesk_hook_email_sent($email, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($email, $success) {
        $hook->onEmailSent($email, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Templates
function hesk_hook_ticket_templates_read($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onTicketTemplatesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_template_created($ticketTemplate, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketTemplate, 'ticket_template')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketTemplate, $success) {
        $hook->onTicketTemplateCreated($ticketTemplate, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_template_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'ticket_template')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketTemplateUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_template_deleted($ticketTemplate, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketTemplate, 'ticket_template')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketTemplate, $success) {
        $hook->onTicketTemplateDeleted($ticketTemplate, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Canned Responses
function hesk_hook_canned_responses_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onCannedResponsesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_canned_response_created($cannedResponse, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $cannedResponse, 'canned_response')) {return;}
    hesk_hooks_call_hook(function($hook) use ($cannedResponse, $success) {
        $hook->onCannedResponseCreated($cannedResponse, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_canned_response_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'canned_response')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onCannedResponseUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_canned_response_deleted($cannedResponse, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $cannedResponse, 'canned_response')) {return;}
    hesk_hooks_call_hook(function($hook) use ($cannedResponse, $success) {
        $hook->onCannedResponseDeleted($cannedResponse, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region KB Categories
function hesk_hook_knowledgebase_category_viewed($knowledgebaseCategory, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseCategory, 'knowledgebase_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseCategory, $success) {
        $hook->onKnowledgebaseCategoryViewed($knowledgebaseCategory, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_category_created($knowledgebaseCategory, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseCategory, 'knowledgebase_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseCategory, $success) {
        $hook->onKnowledgebaseCategoryCreated($knowledgebaseCategory, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_category_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'knowledgebase_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onKnowledgebaseCategoryUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_category_deleted($knowledgebaseCategory, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseCategory, 'knowledgebase_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseCategory, $success) {
        $hook->onKnowledgebaseCategoryDeleted($knowledgebaseCategory, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region KB Articles
function hesk_hook_knowledgebase_article_viewed($knowledgebaseArticle, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseArticle, 'knowledgebase_article')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseArticle, $success) {
        $hook->onKnowledgebaseArticleViewed($knowledgebaseArticle, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_article_created($knowledgebaseArticle, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseArticle, 'knowledgebase_article')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseArticle, $success) {
        $hook->onKnowledgebaseArticleCreated($knowledgebaseArticle, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_article_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'knowledgebase_article')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onKnowledgebaseArticleUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_article_deleted($knowledgebaseArticle, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $knowledgebaseArticle, 'knowledgebase_article')) {return;}
    hesk_hooks_call_hook(function($hook) use ($knowledgebaseArticle, $success) {
        $hook->onKnowledgebaseArticleDeleted($knowledgebaseArticle, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_article_attachment_created($attachment, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $attachment, 'knowledgebase_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($attachment, $success) {
        $hook->onKnowledgebaseArticleAttachmentCreated($attachment, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_knowledgebase_article_attachment_deleted($id, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $id, 'knowledgebase_attachment')) {return;}
    hesk_hooks_call_hook(function($hook) use ($id, $success) {
        $hook->onKnowledgebaseArticleAttachmentDeleted($id, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region KB Search
function hesk_hook_knowledgebase_search_executed($search, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($search, $success) {
        $hook->onKnowledgebaseSearchExecuted($search, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Categories
function hesk_hook_ticket_categories_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onTicketCategoriesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_category_created($ticketCategory, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketCategory, 'ticket_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketCategory, $success) {
        $hook->onTicketCategoryCreated($ticketCategory, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_category_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'ticket_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketCategoryUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_category_deleted($ticketCategory, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketCategory, 'ticket_category')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketCategory, $success) {
        $hook->onTicketCategoryDeleted($ticketCategory, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_email_notification_resent($ticketInfo, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $ticketInfo, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketInfo, $success) {
        $hook->onTicketEmailNotificationResent($ticketInfo, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Search Queries
function hesk_hook_search_query_executed($searchQuery, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($searchQuery, $success, $actor) {
        $hook->onSearchQueryExecuted($searchQuery, hesk_hooks_build_user_info($success, $actor));
    });
}
//endregion
//region Customers
function hesk_hook_customers_viewed($customer_ids, $success = null) {
    if ($success === null) {
        $success = $customer_ids;
        $customer_ids = array();
    }
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer_ids, $success) {
        $user = hesk_hooks_build_user_info($success);
        $user['customer_ids'] = $customer_ids;
        $hook->onCustomersViewed($user);
    });
}
function hesk_hook_customer_search_executed($customer_ids, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer_ids, $success) {
        $hook->onCustomerSearchExecuted($customer_ids, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_customer_created($customer, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $customer, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer, $success) {
        $hook->onCustomerCreated($customer, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_customer_updated($changes, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success, $actor) {
        $hook->onCustomerUpdated($changes, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_customer_deleted($customer, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $customer, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer, $success) {
        $hook->onCustomerDeleted($customer, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_customers_merged($affected_records, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success, $affected_records, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($affected_records, $success, $actor) {
        $hook->onCustomersMerged($affected_records, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_customer_mfa_codes_reset($customer, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $customer, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer, $success) {
        $hook->onCustomerMfaCodesReset($customer, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_customer_mfa_codes_generated($customer, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $customer, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer, $success) {
        $hook->onCustomerMfaCodesGenerated($customer, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_customer_mfa_codes_deleted($customer, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $customer, 'customer')) {return;}
    hesk_hooks_call_hook(function($hook) use ($customer, $success) {
        $hook->onCustomerMfaCodesDeleted($customer, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Private Messages
function hesk_hook_private_message_list_viewed($messages, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($messages, $success) {
        $hook->onPrivateMessageListViewed($messages, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_private_message_viewed($message, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $message, 'private_message')) {return;}
    hesk_hooks_call_hook(function($hook) use ($message, $success) {
        $hook->onPrivateMessageViewed($message, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_private_message_created($message, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $message, 'private_message')) {return;}
    hesk_hooks_call_hook(function($hook) use ($message, $success) {
        $hook->onPrivateMessageCreated($message, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_private_message_status_changed($message, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $message, 'private_message')) {return;}
    hesk_hooks_call_hook(function($hook) use ($message, $success) {
        $hook->onPrivateMessageStatusChanged($message, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_private_message_deleted($message, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $message, 'private_message')) {return;}
    hesk_hooks_call_hook(function($hook) use ($message, $success) {
        $hook->onPrivateMessageDeleted($message, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Team Members
function hesk_hook_team_members_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onTeamMembersViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_created($teamMember, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $teamMember, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($teamMember, $success) {
        $hook->onTeamMemberCreated($teamMember, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $changes, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTeamMemberUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_deleted($teamMember, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $teamMember, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($teamMember, $success) {
        $hook->onTeamMemberDeleted($teamMember, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_mfa_codes_reset($teamMember, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $teamMember, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($teamMember, $success) {
        $hook->onTeamMemberMfaCodesReset($teamMember, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_mfa_codes_generated($teamMember, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $teamMember, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($teamMember, $success) {
        $hook->onTeamMemberMfaCodesGenerated($teamMember, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_team_member_mfa_codes_deleted($teamMember, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $teamMember, 'staff')) {return;}
    hesk_hooks_call_hook(function($hook) use ($teamMember, $success) {
        $hook->onTeamMemberMfaCodesDeleted($teamMember, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Permission Groups
function hesk_hook_permission_groups_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onPermissionGroupsViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_permission_group_created($permissionGroup, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($permissionGroup, $success) {
        $hook->onPermissionGroupCreated($permissionGroup, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_permission_group_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onPermissionGroupUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_permission_group_deleted($permissionGroup, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($permissionGroup, $success) {
        $hook->onPermissionGroupDeleted($permissionGroup, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Banned Emails
function hesk_hook_banned_emails_read($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onBannedEmailsRead(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_banned_email_created($bannedEmail, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($bannedEmail, $success) {
        $hook->onBannedEmailCreated($bannedEmail, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_banned_email_deleted($bannedEmail, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($bannedEmail, $success) {
        $hook->onBannedEmailDeleted($bannedEmail, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Muted Emails
function hesk_hook_muted_emails_read($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onMutedEmailsRead(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_muted_email_created($mutedEmail, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($mutedEmail, $success) {
        $hook->onMutedEmailCreated($mutedEmail, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_muted_email_deleted($mutedEmail, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($mutedEmail, $success) {
        $hook->onMutedEmailDeleted($mutedEmail, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Banned IPs
function hesk_hook_banned_ips_read($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onBannedIpsRead(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_banned_ip_created($bannedIp, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($bannedIp, $success) {
        $hook->onBannedIpCreated($bannedIp, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_banned_ip_deleted($bannedIp, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($bannedIp, $success) {
        $hook->onBannedIpDeleted($bannedIp, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Service Messages
function hesk_hook_service_messages_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onServiceMessagesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_service_message_created($serviceMessage, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($serviceMessage, $success) {
        $hook->onServiceMessageCreated($serviceMessage, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_service_message_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onServiceMessageUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_service_message_deleted($serviceMessage, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($serviceMessage, $success) {
        $hook->onServiceMessageDeleted($serviceMessage, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Email Templates
function hesk_hook_email_templates_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onEmailTemplatesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_email_template_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onEmailTemplateUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Custom Fields
function hesk_hook_custom_fields_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onCustomFieldsViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_custom_field_created($customField, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($customField, $success) {
        $hook->onCustomFieldCreated($customField, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_custom_field_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onCustomFieldUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_custom_field_deleted($customField, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($customField, $success) {
        $hook->onCustomFieldDeleted($customField, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Statuses
function hesk_hook_ticket_statuses_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onTicketStatusesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_status_created($ticketStatus, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketStatus, $success) {
        $hook->onTicketStatusCreated($ticketStatus, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_status_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketStatusUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_status_deleted($ticketStatus, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketStatus, $success) {
        $hook->onTicketStatusDeleted($ticketStatus, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Ticket Priorities
function hesk_hook_ticket_priorities_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onTicketPrioritiesViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_priority_created($ticketPriority, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketPriority, $success) {
        $hook->onTicketPriorityCreated($ticketPriority, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_priority_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onTicketPriorityUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_priority_deleted($ticketPriority, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($ticketPriority, $success) {
        $hook->onTicketPriorityDeleted($ticketPriority, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region OAuth Providers
function hesk_hook_oauth_providers_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onOauthProvidersViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_oauth_provider_created($oauthProvider, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($oauthProvider, $success) {
        $hook->onOauthProviderCreated($oauthProvider, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_oauth_provider_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onOauthProviderUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_oauth_provider_deleted($oauthProvider, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($oauthProvider, $success) {
        $hook->onOauthProviderDeleted($oauthProvider, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Settings
function hesk_hook_settings_viewed($settingsPage, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($settingsPage, $success) {
        $hook->onSettingsViewed($settingsPage, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_settings_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onSettingsUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Audit Log
function hesk_hook_audit_log_viewed($view, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($view, $success) {
        $hook->onAuditLogViewed($view, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_audit_log_export_action($details, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($details, $success, $actor) {
        $hook->onAuditLogExportAction($details, hesk_hooks_build_user_info($success, $actor));
    });
}
//endregion
//region Reports / Export Tickets
function hesk_hook_report_executed($filter, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($filter, $success) {
        $hook->onReportExecuted($filter, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_exported($filter, $success) {
    if (hesk_hooks_redirect_permission_failure($success, $filter, 'ticket')) {return;}
    hesk_hooks_call_hook(function($hook) use ($filter, $success) {
        $hook->onTicketExported($filter, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_export_downloaded($exportId, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($exportId, $success) {
        $hook->onTicketExportDownloaded($exportId, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_ticket_export_deleted($exportId, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($exportId, $success, $actor) {
        $hook->onTicketExportDeleted($exportId, hesk_hooks_build_user_info($success, $actor));
    });
}
//endregion
//region Module - Escalate
function hesk_hook_escalation_rules_viewed($success, $rule_id = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success, $rule_id) {
        $user = hesk_hooks_build_user_info($success);
        $user['escalation_rule_id'] = $rule_id;
        $hook->onEscalationRulesViewed($user);
    });
}
function hesk_hook_escalation_rule_created($escalationRule, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($escalationRule, $success) {
        $hook->onEscalationRuleCreated($escalationRule, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_escalation_rule_updated($changes, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success) {
        $hook->onEscalationRuleUpdated($changes, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_escalation_rule_deleted($escalationRule, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($escalationRule, $success) {
        $hook->onEscalationRuleDeleted($escalationRule, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_escalation_rules_backup_exported($details, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($details, $success) {
        $hook->onEscalationRulesBackupExported($details, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_escalation_rules_backup_restored($details, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($details, $success) {
        $hook->onEscalationRulesBackupRestored($details, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Module - Recurring Tickets
function hesk_hook_recurring_tickets_viewed($success, $recurringTickets = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($recurringTickets, $success) {
        $user = hesk_hooks_build_user_info($success);
        $user['recurring_tickets'] = $recurringTickets;
        $hook->onRecurringTicketsViewed($user);
    });
}
function hesk_hook_recurring_ticket_created($recurringTicket, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($recurringTicket, $success) {
        $hook->onRecurringTicketCreated($recurringTicket, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_recurring_ticket_updated($changes, $success, $actor = null) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($changes, $success, $actor) {
        $hook->onRecurringTicketUpdated($changes, hesk_hooks_build_user_info($success, $actor));
    });
}
function hesk_hook_recurring_ticket_deleted($recurringTicket, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($recurringTicket, $success) {
        $hook->onRecurringTicketDeleted($recurringTicket, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_recurring_tickets_backup_exported($details, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($details, $success) {
        $hook->onRecurringTicketsBackupExported($details, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_recurring_tickets_backup_restored($details, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($details, $success) {
        $hook->onRecurringTicketsBackupRestored($details, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Module - Satisfaction
function hesk_hook_satisfaction_module_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onSatisfactionModuleViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_satisfaction_module_updated($settings, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($settings, $success) {
        $hook->onSatisfactionModuleUpdated($settings, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_satisfaction_module_opt_out_viewed($success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($success) {
        $hook->onSatisfactionModuleOptOutViewed(hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_satisfaction_module_opt_out_created($email, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($email, $success) {
        $hook->onSatisfactionModuleOptOutCreated($email, hesk_hooks_build_user_info($success));
    });
}
function hesk_hook_satisfaction_module_opt_out_deleted($id, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($id, $success) {
        $hook->onSatisfactionModuleOptOutDeleted($id, hesk_hooks_build_user_info($success));
    });
}
//endregion
//region Module - Statistics
function hesk_hook_statistics_viewed($filter, $success) {
    if (hesk_hooks_redirect_permission_failure($success)) {return;}
    hesk_hooks_call_hook(function($hook) use ($filter, $success) {
        $hook->onStatisticsViewed($filter, hesk_hooks_build_user_info($success));
    });
}
//endregion

//region Incoming Email
function hesk_hook_email_import($action, $data = array(), $success = true) {
    hesk_hooks_call_hook(function($hook) use ($action, $data, $success) {
        $user = hesk_hooks_build_user_info($success, array('actor_type' => 'SYSTEM', 'user_id' => null, 'auth_method' => 'NONE'));
        $user['session_ref'] = null;
        $hook->onEmailImport($action, $data, $user);
    });
}
//endregion

//region Base Hook Logic
function hesk_load_hooks() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    // Scan /hooks directory for files
    $root = __DIR__ . '/../';
    foreach (glob($root.'hooks/*.php') as $filename) {
        require_once($filename);
    }

    // Scan /modules/*/hooks directories for files
    foreach (glob($root.'modules/*/hooks/*.php') as $filename) {
        require_once($filename);
    }

    $loaded = true;
}

function hesk_hooks_get_loaded_hook_classes() {
    static $hookClasses = null;

    if ($hookClasses !== null) {
        return $hookClasses;
    }

    hesk_load_hooks();

    $hookClasses = array();

    foreach (get_declared_classes() as $className) {
        if ($className === 'AbstractHook') {
            continue;
        }

        if (is_subclass_of($className, 'Hesk\Hook\AbstractHook')) {
            $hookClasses[] = $className;
        }
    }

    return $hookClasses;
}

function hesk_hooks_get_hook_instances() {
    static $hookInstances = null;

    if ($hookInstances !== null) {
        return $hookInstances;
    }

    $hookInstances = array();

    foreach (hesk_hooks_get_loaded_hook_classes() as $hookClass) {
        try {
            $hookInstances[] = new $hookClass();
        } catch (Exception $e) {
            hesk_hooks_log_exception($hookClass, $e);
        }
    }

    return $hookInstances;
}

function hesk_hooks_log_exception($hookClass, Exception $e) {
    $hookMethod = 'callback';
    foreach ($e->getTrace() as $trace) {
        if (isset($trace['class']) && $trace['class'] === $hookClass && !empty($trace['function'])) {
            $hookMethod = $trace['function'];
            break;
        }
    }

    error_log('HESK HOOK ERROR: ' . $hookClass . '::' . $hookMethod . ' failed with ' . get_class($e) . '.');
}

function hesk_hooks_apply_filter($value, Closure $callback) {
    foreach (hesk_hooks_get_hook_instances() as $hook) {
        try {
            $filtered_value = $callback($hook, $value);
            if ($filtered_value !== null) {
                $value = $filtered_value;
            }
        } catch (Exception $e) {
            hesk_hooks_log_exception(get_class($hook), $e);
        }
    }

    return $value;
}

function hesk_hooks_call_hook(Closure $callback) {
    foreach (hesk_hooks_get_hook_instances() as $hook) {
        try {
            $callback($hook);
        } catch (Exception $e) {
            hesk_hooks_log_exception(get_class($hook), $e);
        }
    }
}

function hesk_hooks_build_user_info($success, $actor = null) {
    static $requestId = null;
    if ($requestId === null) {
        $requestId = bin2hex(random_bytes(16));
    }

    $actor_types = [
        'SYSTEM' => 0,
        'STAFF' => 1,
        'CUSTOMER' => 2
    ];
    $auth_methods = [
        'NONE' => 0,
        'PASSWORD' => 1,
        'MFA' => 2,
        'TOKEN' => 3
    ];

    $current_auth_method = 'NONE';
    $session_ref = null;
    $user_id = null;
    $actor_type = $actor_types['SYSTEM'];
    if (isset($_SESSION['customer']['id'])) {
        $user_id = $_SESSION['customer']['id'];
        $actor_type = $actor_types['CUSTOMER'];
        $session_auth_method = hesk_SESSION(array('customer', 'auth_method'), 'NONE');
        $session_ref = hesk_SESSION(array('customer', 'session_ref'), null);
        if (isset($auth_methods[$session_auth_method])) {
            $current_auth_method = $session_auth_method;
        }
    } elseif (isset($_SESSION['id'])) {
        $user_id = $_SESSION['id'];
        $actor_type = $actor_types['STAFF'];
        $session_auth_method = hesk_SESSION('auth_method', 'NONE');
        $session_ref = hesk_SESSION('session_ref', null);
        if (isset($auth_methods[$session_auth_method])) {
            $current_auth_method = $session_auth_method;
        }
    }

    if (is_array($actor)) {
        if (array_key_exists('user_id', $actor)) {
            $user_id = $actor['user_id'] === null ? null : intval($actor['user_id']);
        }
        if (isset($actor['actor_type']) && isset($actor_types[$actor['actor_type']])) {
            $actor_type = $actor_types[$actor['actor_type']];
        }
        if (isset($actor['auth_method']) && isset($auth_methods[$actor['auth_method']])) {
            $current_auth_method = $actor['auth_method'];
        }
    }

    return [
        'user_id' => $user_id,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
        'ip' => hesk_getClientIP(),
        'session_ref' => $session_ref,
        'request_id' => $requestId,
        'actor_type' => $actor_type,
        'auth_method' => $auth_methods[$current_auth_method],
        'success' => $success
    ];
}
//endregion
