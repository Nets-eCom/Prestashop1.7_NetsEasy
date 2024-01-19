{if isset($error_message->amount.0)}
  <p class="text-danger">{l s="payment_amount_error" d="Modules.Netseasy.Payment_error"}</p>
{else}
  <p class="text-danger">{l s="payment_id_error" d="Modules.Netseasy.Payment_error"}</p>
{/if}
