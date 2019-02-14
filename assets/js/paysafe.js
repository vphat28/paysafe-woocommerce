function cctoken(){}
cctoken.prototype.credit = function(){
	jQuery("#mer_paysafe-token-form").slideUp();
	jQuery("#wc-mer_paysafe-cc-form").slideDown();	
}
cctoken.prototype.token = function(){
	jQuery("#mer_paysafe-token-form").slideDown();
	jQuery("#wc-mer_paysafe-cc-form").slideUp();
	jQuery("input:radio[name=mer_paysafe-token-number]:first").attr('checked', true);
}
var newCredit = new cctoken();
function paysafetoken() {
	newCredit.token();
}
function paysafecc() {
	newCredit.credit();	
}

