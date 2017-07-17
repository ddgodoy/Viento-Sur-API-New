(function($) {
    $.fn.jScroll = function(e) {
        var f = $.extend({}, $.fn.jScroll.defaults, e);
        return this.each(function() {
            var a = $(this);
            var b = $(window);
            var c = new location(a);
            b.scroll(function() {
                a.stop().animate(c.getMargin(b), f.speed)
            })
        });

        function location(d) {
            this.min = d.offset().top;
            this.originalMargin = parseInt(d.css("margin-top"), 10) || 0;
            this.getMargin = function(a) {

                var b = d.parent().height() - d.outerHeight();
                var c = this.originalMargin;
                if (a.scrollTop() >= this.min) c = c + f.top + a.scrollTop() - this.min;

                if (c > b) {
                    // c = b;
                    return false;
                }else{
                    return ({
                        "marginTop": c + 'px'
                    })
                }



            }
        }
    };
    $.fn.jScroll.defaults = {
        speed: "slow",
        top: 10
    }
})(jQuery);