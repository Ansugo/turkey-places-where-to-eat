jQuery(document).ready(function($) {
    // Şehir koordinatlarını otomatik tamamlama
    if ($('#tpwe_autocomplete').length) {
        initAutocomplete();
    }
    
    // Manuel çekme işlemi sırasında yükleme göstergesi
    $(document).on('submit', 'form[action*="tpwe_manual_fetch"]', function(e) {
        var button = $(this).find('button[type="submit"]');
        var originalText = button.text();
        
        button.prop('disabled', true).text('Çekiliyor...');
        
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 30000); // 30 saniye sonra eski haline döner
        
        return true;
    });
    
    // Şehir silme onayı
    $(document).on('submit', 'form[action*="tpwe_delete_city"]', function(e) {
        var cityName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
        return confirm(cityName + ' şehrini ve tüm mekan verilerini silmek istediğinize emin misiniz?');
    });
    
    // Google Places Autocomplete fonksiyonu
    function initAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            // Google Maps API yüklenmemişse, yükle
            loadGoogleMapsAPI();
            return;
        }
        
        var input = document.getElementById('tpwe_autocomplete');
        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['(cities)'],
            componentRestrictions: { country: 'tr' } // Sadece Türkiye
        });
        
        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();
            
            if (!place.geometry) {
                console.log("Place details not found for: " + place.name);
                return;
            }
            
            // Koordinatları form alanlarına doldur
            $('#city_name').val(place.name);
            $('#latitude').val(place.geometry.location.lat());
            $('#longitude').val(place.geometry.location.lng());
            
            // Şehir ID'sini ara (place_id kullanarak)
            if (place.place_id) {
                $('#google_place_id').val(place.place_id);
            }
        });
    }
    
    // Google Maps API yükleme fonksiyonu
    function loadGoogleMapsAPI() {
        var script = document.createElement('script');
        var apiKey = $('#api_key').val() || '';
        
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&libraries=places&callback=initAutocomplete';
        script.async = true;
        script.defer = true;
        
        document.head.appendChild(script);
    }
    
    // API key değiştiğinde bildirim göster
    $('#api_key').on('change', function() {
        if ($(this).val().length > 0) {
            showNotice('API key güncellendi. Sayfayı yenileyerek otomatik tamamlamayı kullanabilirsiniz.', 'success');
        }
    });
    
    // Toplu işlemler
    $('#tpwe_bulk_actions').on('change', function() {
        var action = $(this).val();
        
        if (action === 'fetch_selected') {
            var selectedCities = [];
            $('input[name="city_ids[]"]:checked').each(function() {
                selectedCities.push($(this).val());
            });
            
            if (selectedCities.length === 0) {
                showNotice('Lütfen işlem yapmak için şehir seçin.', 'error');
                return;
            }
            
            // AJAX ile seçili şehirleri çek
            fetchSelectedCities(selectedCities);
        }
        
        $(this).val('');
    });
    
    function fetchSelectedCities(cityIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpwe_fetch_selected_cities',
                city_ids: cityIds,
                nonce: tpwe_admin.nonce
            },
            beforeSend: function() {
                $('#tpwe_loading').show();
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            },
            complete: function() {
                $('#tpwe_loading').hide();
            }
        });
    }
    
    // Bildirim gösterme fonksiyonu
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.tpwe-admin').prepend(notice);
        
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Şehir aktif/pasif toggle
    $('.tpwe-toggle-status').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var cityId = button.data('city-id');
        var currentStatus = button.data('status');
        var newStatus = currentStatus === '1' ? '0' : '1';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpwe_toggle_city_status',
                city_id: cityId,
                status: newStatus,
                nonce: tpwe_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.data('status', newStatus);
                    
                    var newText = newStatus === '1' ? 'Aktif' : 'Pasif';
                    var newClass = newStatus === '1' ? 'status-active' : 'status-inactive';
                    
                    button.text(newText).removeClass('status-active status-inactive').addClass(newClass);
                    
                    showNotice('Durum güncellendi.', 'success');
                }
            }
        });
    });
});