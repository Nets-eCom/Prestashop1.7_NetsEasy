{*
* Prestaworks AB
*
* NOTICE OF LICENSE
*
* This source file is subject to the End User License Agreement(EULA)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://license.prestaworks.se/license.html
*
* @author Prestaworks AB <info@prestaworks.se>
* @copyright Copyright Prestaworks AB (https://www.prestaworks.se/)
* @license http://license.prestaworks.se/license.html
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-envelope"></i>&nbsp;{l s='Easy' mod='easycheckout'}<span class="badge">{$private_messages_count}</span>
        <button type="button" id="show-easy-messages" class="btn btn-primary" style="margin: 3px;">{l s='Show messages' mod='easycheckout'}</button>
    </div>
    <div class="panel panel-highlighted" id="easy-messages" style="padding: 0; margin: 0; display: none;">
        <div class="message-item">
            {foreach $private_messages as $message}
                <div class="message-body" style="margin: 0;">
                    <span class="message-date">&nbsp;<i class="icon-calendar"></i>&nbsp;{$message.date_add}</span>
                    <h4 class="message-item-heading">&nbsp;<span class="badge badge-info">Privat</span></h4>
                    <p class="message-item-text">{$message.message}</p>
                </div>
            {/foreach}
        </div>
    </div>
</div>

{if isset($subscription_message) AND $subscription_message != ''}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-money"></i>&nbsp;{l s='Easy Manage Subscriptions' mod='easycheckout'}<span class="badge">1</span>
            <button type="button" id="show-easy-subscriptions" class="btn btn-primary" style="margin: 3px;">{l s='Manage subscription' mod='easycheckout'}</button>
        </div>
        <div class="panel panel-highlighted" id="easy-subscriptions" style="padding: 0; margin: 0; display: none;">
            <div class="message-item">
                <div class="message-body" style="margin: 0;">
                    <h4 class="message-item-heading">&nbsp;<span class="badge badge-info">{l s='Subscription Info' mod='easycheckout'}</span></h4>
                    <p class="message-item-text subsription"><strong>{$subscription_message}</strong></p>
                    <p class="message-item-text subsription"><strong>{l s='Interval for subscription is' mod='easycheckout'}</strong> {$interval} {l s='day/s' mod='easycheckout'}</p>
                    <p class="message-item-text subsription"><strong>{l s='Subscription end date is' mod='easycheckout'}</strong> {$end_date_subscription}</p>
                    <p class="message-item-text subsription"><strong>{l s='Last charge' mod='easycheckout'}:</strong> {$charge_message}</p>
                </div>
                <br />
                <button type="button" id="charge-easy-subscription" class="btn btn-primary" style="margin: 3px;">{l s='Charge subscription' mod='easycheckout'}</button>
            </div>
        </div>
    </div>
{/if}

<script>
    easy_show_text = "{l s='Show messages' mod='easycheckout'}";
    easy_hide_text = "{l s='Hide messages' mod='easycheckout'}";
    $(document).ready( function() {
        $('#show-easy-messages').on('click', function () {
             if ($('#easy-messages').is(":visible")) {
                $('#show-easy-messages').html(easy_show_text);
             } else {
                $('#show-easy-messages').html(easy_hide_text);
             }
             $('#easy-messages').slideToggle();
             
        });
    });
    
    $(document).ready( function() {
        $('#show-easy-subscriptions').on('click', function () {
			if ($('#easy-subscriptions').is(":visible")) {
				$('#show-easy-subscriptions').html(easy_show_text);
			} else {
				$('#show-easy-subscriptions').html(easy_hide_text);
			}
			$('#easy-subscriptions').slideToggle();
        });
    });
</script>