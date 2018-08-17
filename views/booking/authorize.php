<form action="/booking/authorize" method="post">
    <div class="form-group">
        <label for="FirstName">First Name</label>
        <input type="text" name="FirstName" class="form-control" id="FirstName" placeholder="First Name">
    </div>
    <div class="form-group">
        <label for="LastName">Last Name</label>
        <input type="text" name="LastName" class="form-control" id="LastName" placeholder="Last Name">
    </div>
    <div class="form-group">
        <label for="CartNumber">Cart Number*</label>
        <input type="text" name="CartNumber" class="form-control" id="CartNumber" placeholder="(enter number without spaces or dashes)">
    </div>
    <div class="form-group">
        <label for="ExpirationDate">Expiration Date*</label>
        <input type="text" name="ExpirationDate" class="form-control" id="ExpirationDate" placeholder="(mmyy)">
    </div>
    <button type="submit" class="btn btn-primary" name="SendAuthorize">Send</button>
</form>
