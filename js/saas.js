var SaaS = {
	post : '',
	get : '',
	cookie : '',
	module : '',
	page : '',
	include : '',
	url : '',

	init : function() {
		//this.showPage();
	},

	showPage : function() {
		alert(this.url);
		new Ajax.Request(this.url, {
			method : "post",
			parameters : {
				post : SaaS.post,
				get : SaaS.get,
				page : SaaS.page,
				module : SaaS.module,
				cookie : SaaS.cookie,
				include : SaaS.include
			},
			onComplete : function(request) {
				//alert(request.responseText);
				var divTag=$(this.divtag);
				divTag.innerHTML = request.responseText;
			}.bind(this)
		});
	}
};

$(document).observe('dom:loaded', function() {
	SaaS.init();
});
