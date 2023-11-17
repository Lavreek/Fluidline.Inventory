const $ = require('jquery');

class HeaderSearch {
    eraseAnswers(answers) {
        $('#' + answers).empty();
    }

    setUniqSearch(answers, uniqid) {
        $('#' + answers).attr('data-fl-search', uniqid);
    }

    searchTitle(input) {
        let answers = $(input).attr('data-fl-target');

        this.eraseAnswers(answers);

        let uniqid = Date.now();
        this.setUniqSearch(answers, uniqid);

        if ($(input).val() !== "") {
            this.sendRequest(this, input, uniqid);
        }
    }

    buildCard(search, answers, response) {
        console.log(response);

        let card = document.createElement('div');
        card.className = "d-flex w-100 searched-product";

        let div = document.createElement('div');
        div.className = "d-flex align-items-center justify-content-center searched-image-block";

        let img = document.createElement('img');
        img.className = "mw-100 mh-100 searched-image-content";
        img.src = response.attachments.image;
        img.alt = response.code;

        div.appendChild(img)

        let link = document.createElement('a');
        link.className = "w-100 text-decoration-none fl_font-family-primary searched-link";
        link.href = "/goods/" + response.code;
        link.innerHTML = "Показать подробнее";

        card.appendChild(div);
        card.appendChild(search.buildDescription(response));
        card.appendChild(link);
        answers[0].appendChild(card);
    }

    buildDescription(response) {
        let description = document.createElement('div');
        description.className = "d-flex flex-column fl_font-family-primary searched-description";

        let type = document.createElement('h2');
        type.innerHTML = response.type;

        let code = document.createElement('h1');
        code.innerHTML = response.code;

        let priceBlock = document.createElement('div');
        priceBlock.className = "d-flex justify-content-between w-100 fl_font-family-primary searched-price";

        let priceCurrency = document.createElement('p');
        priceCurrency.innerHTML = response.price.currency;

        let priceValue = document.createElement('span');
        priceValue.innerHTML = response.price.value;

        priceCurrency.appendChild(priceValue);

        let warehouseCount = document.createElement('p');
        warehouseCount.innerHTML = "шт.";

        let warehouseValue = document.createElement('span');
        warehouseValue.innerHTML = response.price.warehouse;

        warehouseCount.prepend(warehouseValue);

        priceBlock.appendChild(priceCurrency);
        priceBlock.appendChild(warehouseCount);

        description.appendChild(type);
        description.appendChild(code);
        description.appendChild(priceBlock);

        return description;
    }

    sendRequest(search, input, uniqid) {
        $.ajax({
            type: "POST",
            url: "/search/full",
            data: {
                "limit" : 24,
                "code" : $(input).val()
            },
            enctype: 'application/json',
            dataType: 'json',
            statusCode: {
                200: function (response) {
                    if (response !== undefined) {
                        if (response['search']) {
                            let answers = $('#' + $(input).attr('data-fl-target') );

                            for (let i = 0; i < response['search'].length; i++) {
                                if (Number($(answers).attr('data-fl-search')) !== uniqid) {
                                    return;
                                }

                                search.buildCard(search, answers, response.search[i]);
                            }
                        }
                    }
                },
            }
        });
    }
}

let input = $("input[name=searchInput]");

if (input[0] !== undefined) {
    let search = new HeaderSearch();

    $("input[name=searchInput]").on('input', function () {
        search.searchTitle($(this))
    });
}
