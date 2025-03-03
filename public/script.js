const submit_btn = document.getElementById("submit");
const data_block = document.getElementById("data");
const data_table = document.getElementById("report");
const user_name = document.getElementById("user_name");

submit_btn.onclick = function (e) {
  e.preventDefault();
  data_block.style.display = "none";

  const form = e.target.form;
  const users = form.querySelector('select[name="user"]');

  const selected_user_id = users.value;
  user_name.innerHTML = users.options[users.selectedIndex].text;

  //ajax request
  fetch('/data.php?' + new URLSearchParams({
    user: selected_user_id
  }))
    .then(response => response.json())
    .then(data => {

      // Clear existing table rows
      while (data_table.getElementsByTagName('tr').length > 0) {
        data_table.deleteRow(0);
      }

      // Add new rows with data
      data.forEach(row => {
        const tr = data_table.insertRow();
        Object.values(row).forEach(value => {
          const td = tr.insertCell();
          td.textContent = value;
        });
      });
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to fetch data');
    });

  data_block.style.display = "block";
};
