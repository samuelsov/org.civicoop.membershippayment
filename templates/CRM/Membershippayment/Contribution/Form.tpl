{capture name="membership_payment" assign="membership_payment"}
    <tr class="crm-membership_payment-form-block-membership_id">
        <td class="label">{$form.membership_id.label}</td>
        <td>{$form.membership_id.html}</td>
    </tr>
{/capture}
{capture name="member_contact" assign="member_contact"}
        <tr class="crm-membership_payment-form-block-member_contact">
            <td class="label">{$form.member_contact.label}</td>
            <td>{$form.member_contact.html}</td>
        </tr>
{/capture}
<script type="text/javascript">
    {literal}
    cj(function() {
        cj('tr.crm-contribution-form-block-contribution_status_id').after('{/literal}{$membership_payment|escape:'javascript'}{literal}');
        cj('tr.crm-membership_payment-form-block-membership_id').before('{/literal}{$member_contact|escape:'javascript'}{literal}');

        cj('#member_contact').change(function(){
            var cid = cj(this).val();
            CRM.api3('Membership', 'get', {
              "sequential": 1,
              "contact_id": cid,
              "api.Contact.getsingle": {"id":"$value.contact_id","return":"display_name"},
              "api.MembershipStatus.getsingle": {"id":"$value.status_id","return":"label"}
            }).done(function(result) {
               var data = result.values;
               var options = '<option value>-- None --</option>';
               for(x = 0; x < result.count; x++) {
                 var displayName = data[x]['api.Contact.getsingle']['display_name'];
                 var statusLabel = data[x]['api.MembershipStatus.getsingle']['label'];
                 var label = displayName + " - " + data[x].membership_name + ": " + statusLabel + " (" + data[x].start_date + " - " + data[x].end_date + " )";
                 options += '<option value="' + data[x]['id'] + '">' + label + '</option>';
               }
               cj('#membership_id').html(options);
            });
        });
    });
    {/literal}
</script>
