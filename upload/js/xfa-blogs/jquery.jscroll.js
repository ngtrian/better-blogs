
!function($, window, document, _undefined) {

	/**
	 * Constructor for the Infinite Scroll object
	 */
	var InfiniteScroll = function(ele) {
		this.ele = ele;
		this.url = ele.data('url');
		this.currentPage = ele.data('currentPage') ? ele.data('currentPage') : 1;
		this.totalPages = Math.ceil(ele.data('totalPages'), 10);
		this.updateInProgress = false;
		this.totalPagesLoaded = 0;
		this.maxPagesToLoad = 10;
	};
	
	InfiniteScroll.prototype.setupPos = function() {
		this.nextUrl = this.url.replace(/PAGE/g, ++this.currentPage);
		this.loadResultsAt = this.getBottomCord();
	};
	
	InfiniteScroll.prototype.start = function() {
		this.setupPos();
		var thisObj = this;
		$(window).scroll(function(e) {
			thisObj.handleScrollFunction();
		});
	};	
	
	InfiniteScroll.prototype.getBottomCord = function() {
		// calculate the "bottom border" of the container
		var bottom = 0;
		bottom += this.ele.offset().top;
		bottom += parseInt(this.ele.css('borderTopWidth'), 10);
		bottom += parseInt(this.ele.css('paddingTop'), 10);
		bottom += parseInt(this.ele.height(), 10);
		
		// and now, remove the window size
		bottom -= $(window).height();
		// a confidence value to start loading a little before
		bottom -= 100;
		
		bottom = parseInt(bottom, 10);
		console.log('Will load results when we scroll at position ' + bottom);
		return bottom;
	};
	
	InfiniteScroll.prototype.handleScrollFunction = function() {
		var scrollTop = $(window).scrollTop();
		// console.log(scrollTop);
		
		if (scrollTop < this.loadResultsAt) {
			return;
		}
		if (this.updateInProgress) {
			// console.log("Update in progress");
			return;
		}
		if (this.totalPagesLoaded >= this.maxPagesToLoad) {
			// console.log("Max pages loaded with infinite scroll");
			return;
		}
		if (this.currentPage > this.totalPages) {
			// console.log("Max pages loaded {} of {}", this.currentPage, this.totalPages);	
			return;
		}
		
		this.updateInProgress = true;
		var thisObj = this;
		XenForo.ajax(this.nextUrl, null, function(ajaxData, textStatus) { thisObj.updateContent(ajaxData, textStatus); });
	};
	
	InfiniteScroll.prototype.updateContent = function(ajaxData, textStatus) {
		this.ele.append(ajaxData.templateHtml);
		this.updateInProgress = false;
		this.totalPagesLoaded++;
		this.setupPos();
		
		if (this.totalPagesLoaded >= this.maxPagesToLoad) {
			$(window).off('scroll');
		}
		if (this.currentPage > this.totalPages) {
			$(window).off('scroll');
		}
	};

	XenForo.InfiniteScroll = function(ele) {
		var s = new InfiniteScroll(ele);
		s.start();
	};
	
	XenForo.register('.InfiniteScroll', 'XenForo.InfiniteScroll');

}(jQuery, this, document);
