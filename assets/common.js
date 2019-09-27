// Gestion des notifications
function notification(sucess,header,body) {
  // Création de l'alerte
  $alert = $('<div class="alert alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button></div>');
  // Ajout du titre
  $('<div style="font-weight:bold;"></div>')
    .text(header)
    .appendTo($alert);
  // Ajout des différents messages
  if(!$.isArray(body)) {
    body = [body];
  }
  $(body).each(function (i, message) {
    $('<div></div>')
      .html(message)
      .appendTo($alert);
  });
  // Ajout de la bonne classe
  if (sucess) {
    $alert.addClass('alert-success');
  } else {
    $alert.addClass('alert-danger');
  }
  // Positionement avant le tableau
  $("body > .container").prepend($alert);
}

// Factorisation de la fonction Ajax d'appel à l'API
function apiCall(method,url,data) { // To be used only when a payload shall be sent
  return $.ajax({
    url: "ws.php/"+url,
    type: method,
    processData: false,
    contentType: "application/json; charset=utf-8",
    data: JSON.stringify(data)
  });
}

$(document).ready(function () {
  // Gestion centralisée des erreurs sur requêtes Ajax
  $( document ).ajaxError(function( event, jqxhr, settings, thrownError ) {
    $("#transactions_processing").css('visibility', 'hidden');
    try {
      var answer = jQuery.parseJSON(jqxhr.responseText);
    } catch (e) {
      var answer = jqxhr.responseText;
    }
    notification(false,"Erreur",answer);
  });
});