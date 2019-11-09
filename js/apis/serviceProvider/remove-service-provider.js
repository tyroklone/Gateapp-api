// This script removes service provider
const routes = new Routes();
const removeYes = document.querySelector('#remove-yes');

function removed() {

// Let me make some edits
let remo = sessionStorage.getItem("remo-id");
let result = '';
let str = remo.toString();
let count = str.length;
if(count > 1)
{
 let remain = count - 1;
 result = str.slice(remain);
}
 else
{
 result = str;
}

console.log(result);
const apiUrl = `${routes.api_origin}${routes.removeServiceProvider(result)}`;

  
   
    fetch(apiUrl,{
       
      method: 'DELETE',
      mode: "cors",
        headers: {
            "Accept": "aplication/json",
            "Authorization": token
        }})
   .then(response => {
    return response.json()
  })
  .then(data => {
    // Log data to the console
    console.log(data);
   
    }).catch(err => {
      console.error(err)
    })
    
}

removeYes.addEventListener('click', (event) => { removed() });

