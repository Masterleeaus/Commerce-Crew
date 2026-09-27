<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Http\Resources\Api;

use App\Enums\Introduction;
use App\Helpers\Classes\MarketplaceHelper;
use DB;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ChatbotResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'uuid'                       => $this->uuid,
            'title'                      => $this->title,
            'bubble_message'             => trans($this->bubble_message),
            'welcome_message'            => $this->welcome_message,
            'logo'                       => $this->logo ? asset($this->logo) : null,
            'avatar'                     => $this->avatar ? asset($this->avatar) : null,
            'trigger_avatar_size'        => $this->trigger_avatar_size,
            'trigger_background'         => $this->trigger_background,
            'trigger_foreground'         => $this->trigger_foreground,
            'color_mode'                 => $this->color_mode,
            'color'                      => $this->color,
            'show_logo'                  => $this->show_logo,
            'show_date_and_time'         => $this->show_date_and_time,
            'show_average_response_time' => $this->show_average_response_time,
            'position'                   => $this->position,
            'active'                     => $this->active,
            'interaction_type'           => $this->interaction_type,
            'connect_message'            => $this->connect_message,
            'language'                   => app()->getLocale(),
            'bubble_design'           	  => $this->bubble_design,
            'promo_banner'               => array_filter([
                'image'       => $this->promo_banner_image ? asset($this->promo_banner_image) : null,
                'title'       => $this->promo_banner_title,
                'description' => $this->promo_banner_description,
                'btn_label'   => $this->promo_banner_btn_label,
                'btn_link'    => $this->promo_banner_btn_link,
            ]),
            'links'                      => array_filter([
                'whatsapp'           	 => $this->whatsapp_link,
                'telegram'           	 => $this->telegram_link,
            ]),
            'is_email_collect'           => $this->is_email_collect,
            'is_contact'                 => $this->is_contact,
            'is_attachment'              => $this->is_attachment,
            'is_emoji'                   => $this->is_emoji,
            'is_articles'                => $this->is_articles,
            'is_links'                   => $this->is_links,
            'is_review_enabled'          => MarketplaceHelper::isRegistered('chatbot-review') && $this->is_review_enabled,
            'review_prompt'              => MarketplaceHelper::isRegistered('chatbot-review') ? $this->review_prompt : null,
            'review_responses'           => MarketplaceHelper::isRegistered('chatbot-review') ? ($this->review_responses ?? []) : [],
            'header_bg_type'             => $this->header_bg_type,
            'header_bg_color'            => $this->header_bg_color,
            'header_bg_gradient'         => $this->header_bg_gradient,
            'header_bg_image'            => $this->header_bg_image ? asset($this->header_bg_image) : null,
            'welcome_bg_image'           => $this->welcome_bg_image ? asset($this->welcome_bg_image) : null,
            'suggested_prompts'          => $this->suggested_prompts ?? [],
            'suggested_prompts_enabled'  => (bool) ($this->suggested_prompts_enabled ?? ($this->suggested_prompts && count($this->suggested_prompts) > 0)),
            'voice_call_enabled'         => ($this->user?->isAdmin()) || ((bool) $this->voice_call_enabled && ($this->user?->relationPlan?->checkOpenAiItem(Introduction::AI_EXT_VOICE_CALL->value) ?? true)),
            'voice_call_provider'        => setting('voice_call_provider'),
            'voice_call_first_message'   => $this->voice_call_first_message,
            'unread_count'               => $this->getUnreadCount(),
            'trusted_domains'            => $this->trusted_domains,
            'is_shop'					               => $this->is_shop,
            'shop_source'				            => $this->shop_source,
            'shopify_domain'			          => $this->shopify_domain,
            'shopify_access_token'		     => $this->shopify_access_token,
            'woocommerce_domain'		       => $this->woocommerce_domain,
            'woocommerce_consumer_key'   => $this->woocommerce_consumer_key,
            'woocommerce_consumer_secret'=> $this->woocommerce_consumer_secret,
        ];
    }

    protected function getUnreadCount(): int
    {
        // Get session ID from query parameter
        $sessionId = request()->query('session');

        // If no session ID provided, return 0 (no unread messages to show)
        if (! $sessionId) {
            return 0;
        }

        // Count unread messages from human agents (for external widget badge)
        // Join with conversations table to filter by session
        return DB::table('ext_chatbot_histories')
            ->join('ext_chatbot_conversations', 'ext_chatbot_histories.conversation_id', '=', 'ext_chatbot_conversations.id')
            ->where('ext_chatbot_conversations.chatbot_id', $this->id)
            ->where('ext_chatbot_conversations.session_id', $sessionId)
            ->where('ext_chatbot_histories.role', 'assistant')
            ->whereNull('ext_chatbot_histories.read_at')
            ->count();
    }
}
