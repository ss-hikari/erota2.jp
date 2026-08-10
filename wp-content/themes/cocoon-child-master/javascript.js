//ここに追加したいJavaScript、jQueryを記入してください。
//このJavaScriptファイルは、親テーマのJavaScriptファイルのあとに呼び出されます。
//JavaScriptやjQueryで親テーマのjavascript.jsに加えて関数を記入したい時に使用します。

//サムネイルクリックでオーバーレイ表示
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('fanza-sample-overlay');
    var overlayImg = document.getElementById('fanza-sample-overlay-img');
    var prevBtn = document.getElementById('fanza-sample-prev');
    var nextBtn = document.getElementById('fanza-sample-next');

    if (!overlay) return;

    var thumbs = document.querySelectorAll('.fanza-sample-thumb');
    var largeUrls = Array.prototype.map.call(thumbs, function (t) {
        return t.dataset.large;
    });
    var currentIndex = 0;

    function showImage(index) {
        // ループさせる(最後の次は最初に戻る)
        if (index < 0) index = largeUrls.length - 1;
        if (index >= largeUrls.length) index = 0;
        currentIndex = index;
        overlayImg.src = largeUrls[currentIndex];
    }

    thumbs.forEach(function (thumb, i) {
        thumb.addEventListener('click', function () {
            showImage(i);
            overlay.classList.add('is-active');
        });
    });

    prevBtn.addEventListener('click', function (e) {
        e.stopPropagation(); // オーバーレイのclose処理を発火させない
        showImage(currentIndex - 1);
    });

    nextBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        showImage(currentIndex + 1);
    });

    // オーバーレイの背景クリックで閉じる(矢印・画像クリックでは閉じない)
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            overlay.classList.remove('is-active');
            overlayImg.src = '';
        }
    });

    // キーボードの左右矢印にも対応させたい場合
    document.addEventListener('keydown', function (e) {
        if (!overlay.classList.contains('is-active')) return;
        if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
        if (e.key === 'ArrowRight') showImage(currentIndex + 1);
        if (e.key === 'Escape') {
            overlay.classList.remove('is-active');
            overlayImg.src = '';
        }
    });
});