<?php

namespace App\Services;

use App\Models\ChatDistribution;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Message;
use App\Models\RoutingRule;
use App\Models\RuleActionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RoutingRuleService
{
    private $encryptionService;

    public function __construct(ChannelEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function evaluateRoutingRules($tenantId, $conversationId, $contactId, $context, $channelConfig = [])
    {
        Log::info('Evaluating routing rules with context', $context);

        $rules = RoutingRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('priority', 'asc')
            ->get();

        if ($rules->isEmpty()) {
            Log::info('No active routing rules found');

            return [];
        }

        $finalAssignedTo = null;

        foreach ($rules as $rule) {
            $conditions = is_string($rule->conditions) ? json_decode($rule->conditions, true) : $rule->conditions;
            $actionsRaw = is_string($rule->actions) ? json_decode($rule->actions, true) : $rule->actions;

            Log::info("Evaluating rule: {$rule->name}");

            $conditionsPassed = $this->evaluateConditionGroup($conditions, $context);

            if ($conditionsPassed) {
                Log::info("Rule \"{$rule->name}\" matched - executing THEN actions");
                $result = $this->executeActions(
                    $actionsRaw['actions'] ?? [],
                    $conversationId,
                    $contactId,
                    $tenantId,
                    $rule->id,
                    $context['channel_type'],
                    $channelConfig
                );
                if (! empty($result['assignedTo'])) {
                    $finalAssignedTo = $result['assignedTo'];
                }
            } elseif (! empty($actionsRaw['elseActions'])) {
                Log::info("Rule \"{$rule->name}\" not matched - executing ELSE actions");
                $result = $this->executeActions(
                    $actionsRaw['elseActions'],
                    $conversationId,
                    $contactId,
                    $tenantId,
                    $rule->id,
                    $context['channel_type'],
                    $channelConfig
                );
                if (! empty($result['assignedTo'])) {
                    $finalAssignedTo = $result['assignedTo'];
                }
            }
        }

        return ['assignedTo' => $finalAssignedTo];
    }

    private function evaluateConditionGroup($group, $context)
    {
        if (empty($group['conditions'])) {
            return true;
        }

        $operator = $group['operator'] ?? 'AND';

        if ($operator === 'AND') {
            foreach ($group['conditions'] as $condition) {
                if (! $this->evaluateCondition($condition, $context)) {
                    return false;
                }
            }

            return true;
        } else {
            foreach ($group['conditions'] as $condition) {
                if ($this->evaluateCondition($condition, $context)) {
                    return true;
                }
            }

            return false;
        }
    }

    private function evaluateCondition($condition, $context)
    {
        $fieldValue = null;

        switch ($condition['field'] ?? '') {
            case 'channel_type':
            case 'message_content':
            case 'message_language':
            case 'time.hour':
            case 'time.day_of_week':
            case 'contact.name':
            case 'contact.email':
            case 'contact.phone':
                $mappedKey = str_replace('.', '_', $condition['field']); // contact.name -> contact_name etc
                $fieldValue = $context[$mappedKey] ?? null;
                break;
            default:
                return false;
        }

        $conditionValue = $condition['value'] ?? null;
        $stringFieldValue = strtolower(trim((string) $fieldValue));

        $operator = $condition['operator'] ?? 'equals';

        switch ($operator) {
            case 'equals':
                return $stringFieldValue === strtolower(trim((string) $conditionValue));
            case 'not_equals':
                return $stringFieldValue !== strtolower(trim((string) $conditionValue));
            case 'contains':
                return str_contains($stringFieldValue, strtolower(trim((string) $conditionValue)));
            case 'not_contains':
                return ! str_contains($stringFieldValue, strtolower(trim((string) $conditionValue)));
            case 'starts_with':
                return str_starts_with($stringFieldValue, strtolower(trim((string) $conditionValue)));
            case 'ends_with':
                return str_ends_with($stringFieldValue, strtolower(trim((string) $conditionValue)));
            case 'greater_than':
                return ((float) $fieldValue) > ((float) $conditionValue);
            case 'less_than':
                return ((float) $fieldValue) < ((float) $conditionValue);
            case 'in':
                if (is_array($conditionValue)) {
                    $arr = array_map('strtolower', array_map('trim', $conditionValue));

                    return in_array($stringFieldValue, $arr);
                }

                return false;
            case 'not_in':
                if (is_array($conditionValue)) {
                    $arr = array_map('strtolower', array_map('trim', $conditionValue));

                    return ! in_array($stringFieldValue, $arr);
                }

                return true;
            default:
                return false;
        }
    }

    private function executeActions($actions, $conversationId, $contactId, $tenantId, $ruleId, $channelType, $channelConfig)
    {
        $result = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            Log::info("Executing action: {$type}");

            switch ($type) {
                case 'assign_to_user':
                    if (! empty($action['user_id'])) {
                        $result['assignedTo'] = $action['user_id'];
                    }
                    break;

                case 'assign_to_pool':
                    if (! empty($action['user_ids']) && is_array($action['user_ids'])) {
                        $userIds = $action['user_ids'];
                        $distribution = ChatDistribution::firstOrCreate(
                            ['tenant_id' => $tenantId],
                            ['assignment_index' => 0]
                        );

                        $poolIndex = $distribution->assignment_index % count($userIds);
                        $result['assignedTo'] = $userIds[$poolIndex];

                        $distribution->update([
                            'assignment_index' => $distribution->assignment_index + 1,
                            'last_assigned_user_id' => $result['assignedTo'],
                        ]);
                    }
                    break;

                case 'apply_status':
                    if (! empty($action['status_id'])) {
                        $result['statusId'] = $action['status_id'];
                        ContactStatus::updateOrCreate(
                            ['tenant_id' => $tenantId, 'contact_id' => $contactId, 'status_id' => $action['status_id']],
                            []
                        );
                    }
                    break;

                case 'send_message':
                    if (! empty($action['content']) && $conversationId) {
                        $result['autoReplyContent'] = $action['content'];

                        Message::create([
                            'tenant_id' => $tenantId,
                            'conversation_id' => $conversationId,
                            'sender_type' => 'system',
                            'content' => $action['content'],
                            'metadata' => ['auto_reply' => true, 'rule_id' => $ruleId],
                        ]);

                        if ($channelType === 'telegram' && ! empty($channelConfig['bot_token'])) {
                            try {
                                $conv = Conversation::with('contact')->find($conversationId);
                                $chatId = $conv->contact->metadata['chat_id'] ?? null;

                                if ($chatId) {
                                    $botToken = $this->encryptionService->decryptCredential($channelConfig['bot_token']);

                                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                        'chat_id' => $chatId,
                                        'text' => $action['content'],
                                    ]);
                                    Log::info('Auto-reply sent via Telegram');
                                }
                            } catch (\Exception $err) {
                                Log::error('Error sending Telegram auto-reply: '.$err->getMessage());
                            }
                        }
                    }
                    break;
            }

            RuleActionLog::create([
                'tenant_id' => $tenantId,
                'rule_id' => $ruleId,
                'conversation_id' => $conversationId ?: null,
                'contact_id' => $contactId ?: null,
                'action_type' => $type,
                'action_details' => $action,
                'executed_at' => now(),
            ]);
        }

        return $result;
    }

    public function getNextSalesRep($tenantId)
    {
        $salesReps = Membership::where('tenant_id', $tenantId)
            ->where('role', 'sales')
            ->where('is_active', true)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($salesReps->isEmpty()) {
            Log::info('No sales reps found for tenant: '.$tenantId);

            return null;
        }

        $distribution = ChatDistribution::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['assignment_index' => 0]
        );

        $nextIndex = $distribution->assignment_index % $salesReps->count();
        $nextSalesRep = $salesReps[$nextIndex]->user_id;

        $distribution->update([
            'assignment_index' => $distribution->assignment_index + 1,
            'last_assigned_user_id' => $nextSalesRep,
        ]);

        Log::info("Assigned to sales rep at index {$nextIndex}: {$nextSalesRep}");

        return $nextSalesRep;
    }
}
