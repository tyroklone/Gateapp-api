// This script suspends service provider 
const routes = new Routes();
const suspendYes = document.querySelector('#suspend-yes');

function suspended() {

// Let me make some edits
let susp = sessionStorage.getItem("sus-id");
let result = '';
let str = susp.toString();
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

const apiUrl = `${routes.api_origin}${routes.suspendServiceProvider(result)}`;

  
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
    // Log data to console
    console.log(data);
   
    }).catch(err => {
      console.error(err)
    })
    
}

suspendYes.addEventListener('click', (event) => { suspended() });
