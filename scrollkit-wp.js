// Generated by CoffeeScript 1.4.0
(function() {
  var $, apiEndpoint;

  $ = jQuery;

  apiEndpoint = 'http://localhost:3000/api';

  $('#scrollkit-wp-convert').on('click', function(e) {
    var data,
      _this = this;
    data = {
      title: $('#title').val(),
      content: window.tinymce.activeEditor.getContent(),
      id: $('#post_ID').val()
    };
    return $.ajax({
      type: "POST",
      url: "" + apiEndPoint + "/new",
      data: data,
      error: function(jqXHR) {
        alert(jqXHR.responseText);
        return console.log(jqXHR);
      },
      success: function(data) {
        alert("B-)");
        return console.log(data);
      }
    });
  });

}).call(this);
