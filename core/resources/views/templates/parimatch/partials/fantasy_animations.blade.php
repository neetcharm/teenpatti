<!-- Fantasy Animations Overlay -->
<div id="fantasy-overlay">
    <div id="lottie-container"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

<style>
    #fantasy-overlay {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        pointer-events: none;
        z-index: 99999;
        display: none;
        justify-content: center;
        align-items: center;
    }
    #fantasy-overlay.active {
        display: flex;
    }
    #lottie-container {
        width: 100%;
        height: 100%;
        max-width: 800px;
        max-height: 800px;
    }
</style>

<script>
    "use strict";

    let lottieInstance = null;

    // Global function to trigger a 'Fantasy Win' animation
    window.triggerFantasyWin = function(amount = 0) {
        var overlay = document.getElementById('fantasy-overlay');
        var container = document.getElementById('lottie-container');
        overlay.classList.add('active');
        container.innerHTML = ''; // clear previous

        lottieInstance = lottie.loadAnimation({
            container: container,
            renderer: 'svg',
            loop: false,
            autoplay: true,
            // Using a well-known Lottie JSON URL for trophy/gold win 
            path: 'https://assets2.lottiefiles.com/packages/lf20_touohxv0.json' 
        });

        lottieInstance.addEventListener('complete', function() {
            overlay.classList.remove('active');
            lottieInstance.destroy();
        });
    };

    // Global function to trigger a 'Fantasy Loss' dark animation
    window.triggerFantasyLoss = function() {
        var overlay = document.getElementById('fantasy-overlay');
        
        // Let's do a simple red pulse for loss using an empty confetti call just to shake or simple DOM class insertion.
        $('body').append('<div class="loss-flash" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,0,0,0.1);z-index:9999;pointer-events:none;animation:fadeOut 1s forwards;"></div>');
        
        setTimeout(function(){
            $('.loss-flash').remove();
        }, 1000);
    };

    $("<style type='text/css'> @keyframes fadeOut{ 0%{opacity:1;} 100%{opacity:0;} } </style>").appendTo("head");

    // Global hooked MutationObserver to automatically detect wins globally across all 23 games.
    document.addEventListener("DOMContentLoaded", function() {
        var targetNode = document.querySelector('.win-loss-popup');
        var observerOptions = {
            attributes: true,
            attributeFilter: ['class'],
            subtree: false
        };

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if(mutation.target.classList.contains('active')) {
                    // It fired a popup. Check if win or lose
                    var isWin = !mutation.target.querySelector('.img-glow.win').classList.contains('d-none');
                    if(isWin) {
                        window.triggerFantasyWin();
                    } else {
                        window.triggerFantasyLoss();
                    }
                }
            });
        });

        if(targetNode) {
            observer.observe(targetNode, observerOptions);
        }
    });

</script>
