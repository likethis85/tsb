function login_slide(selecter){    $(selecter).eq(0).css({        'opacity': 1,        'z-index': 2    }).siblings().css({        'opacity': 0,        'z-index': 1    });    //轮播    setInterval(function ()    {        var num,             arr = ['欢迎来到 同事帮 社区，<br />你想知道的一切，或许答案都在这里。<br /><span>beta版发布！</span>',                    '你看到的是beta版',                    '同事帮 是一个新型的社交社区包括了问答，维基，以及社交等丰富而又轻量化的功能。<br /><span>这将是您的最好选择！</span>'];        //获取当前轮播图的index        $(selecter).each(function () {            if ($(this).css('opacity') == 1)            {                num = $(this).index();            }        });        //隐藏当前那张轮播图        $(selecter).eq(num).animate({            opacity: '0'        }, 500);        //判断如果当前是最后一张的话跳会第一张        if (num + 1 >= $(selecter).length)        {            $(selecter).eq(0).animate({                opacity: '1'            }, 500);            $('.aw-login-state').html(arr[0]);        }        else        {            $(selecter).eq(num + 1).animate({                opacity: '1'            }, 500);            $('.aw-login-state').html(arr[num + 1]);        }    }, 7000);}