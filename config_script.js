function showEditModal(index, ip, type) {
  document.getElementById("editIndex").value = index;
  document.getElementById("editIp").value = ip;
  document.getElementById("editType").value = type;
  document.getElementById("editModal").style.display = "block";
}
function confirmRemove(index) {
  if (confirm("Are you sure you want to remove this device?")) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "manage_devices.php";

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "remove";
    form.appendChild(actionInput);

    const indexInput = document.createElement("input");
    indexInput.type = "hidden";
    indexInput.name = "index";
    indexInput.value = index;
    form.appendChild(indexInput);

    document.body.appendChild(form);
    form.submit();
  }
}
