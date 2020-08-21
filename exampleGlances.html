<!DOCTYPE html>
<html>
   <head>
      <meta http-equiv="content-type" content="text/html; charset=utf-8">
      <script src="vendor/components/jquery/jquery.min.js"></script>
   </head>
<body>

<table><tr>
<td valign="top">
   <form action="pushover-http.php" id="pushover">
     config:    <br><input type="text" id="pushconfig" name="config" size="50"                                      ><br>
     user:      <br><input type="text" id="pushuser" name="user" size="50"                                          ><br>
     token:     <br><input type="text" id="pushtoken" name="token" size="50"                                        ><br>
     device:    <br><input type="text" id="pushdevice" name="device" size="50"                                      ><br>
     title:     <br><input type="text" id="pushtitle" name="title" size="100"                                       ><br>
     text:      <br><input type="text" id="pushtext" name="text" size="100"                                         ><br>
     subtext:   <br><input type="text" id="pushsubtext" name="subtext" size="100"                                   ><br>
     count:     <br><input type="text" id="pushcount " name="count" size="50"                                       ><br>
     percent:   <br><input type="text" id="pushpercent" name="percent" size="5"                                     ><br>

                <br><input type="button" id="pushsubmit" value="glance" size="50" onclick="subPushGlance(this.form);"       >
                    <input type="radio" name="method" value="get" id="pushrbget" checked                            > GET
                    <input type="radio" name="method" value="post" id="pushrbpost"                                  > POST
                    <input type="checkbox" name="debug" value="debug" id="pushdebug"                                > DEBUG

   </form>
</td>
</tr>
<tr>
   <td colspan="2">
   <hr>
   </td>
</tr>
</table>

<!-- the result of the push will be rendered inside this div -->
<div id="result"></div>

</body>
</html>

<script type='text/javascript'>
   /*
    * pushover from submit
    */
   function subPushGlance(form) {
       $("#result").empty();

      /* get some values from elements on the page: */
      url = form.action;

      /* Send the data using post/get */
      data = {
         job: 'glances',
         config: $('#pushconfig').val(),
         user: $('#pushuser').val(),
         token: $('#pushtoken').val(),
         device: $('#pushdevice').val(),
         title: $('#pushtitle').val(),
         text: $('#pushtext').val(),
         subtext: $('#pushsubtext').val(),
         count: $('#pushcount').val(),
         percent: $('#pushpercent').val(),
      };

      if($('#pushdebug').prop("checked"))
         data.echo = 1;

      if($('#pushrbget').prop("checked")) {
         var posting = $.get(url, data);
      } else {
         var posting = $.post(url, data);
      }
      /* Put the results in a div */
      posting.done(function(data) {
         $("#result").append(data);
         var receipt = data.match('[A-Za-z0-9]{30}')
      });
      posting.fail(function(data) {
         $("#result").append("[" + data.status + "] " + data.statusText + "<br>" + data.responseText);
      });
   };

</script>
