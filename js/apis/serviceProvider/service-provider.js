// JS Fetch to get all service providers
const routes = new Routes();
const url = `${routes.api_origin}${routes.allServiceProviders}`;

//Get Dom Elements 
const table = document.querySelector('#sp-tab');
const suspend = document.querySelector('#suspend');
const remove = document.querySelector("#remove");
const singlemodal = document.querySelector('#singleProviderModal');

// Begin the fun stuff
fetch(url, {
        method: 'GET',
        mode: 'cors',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'aplication/json',
            'Authorization': token
        }})
  .then(response => {
    return response.json()
  })
  .then(data => {
    // Work with JSON data here
    console.log(data);
   data.data.forEach(sp => {
    
            // Create Entries placeholders on table 
            let row = table.insertRow(),
                spName = row.insertCell(),
                spEstate = row.insertCell(),
                spPhone = row.insertCell(),
                spDetails = row.insertCell(),
                moreDetails = row.insertCell();
           
            //Insert Response into table
            spName.innerHTML = `${sp.name}`;
            spEstate.innerHTML = `${sp.estate}`;
            spPhone.innerHTML = `${sp.phone}`;
            spDetails.innerHTML = `${sp.description}`;
            moreDetails.innerHTML = '<td><a href="img" data-toggle="modal" data-target="#singleProviderModal" class="green">View Details</a>';
           
            moreDetails.addEventListener('click', (event) => {
                modalName.innerHTML = `${sp.name}`;
                modalPhone.innerHTML = `${sp.phone}`;
                modalEstate.innerHTML = `${sp.description}`;
                modalCat.innerHTML = `${sp.categroy}`;
                modalDesc.innerHTML = `${sp.description}`;
                
                remove.setAttribute('data-id', `${sp.id}`);
                suspend.setAttribute('data-id', `${sp.id}`);
                suspend.addEventListener('click', (event) => {
      
                sessionStorage.setItem('sus-id', suspend.getAttribute('data-id'));
                console.log(sessionStorage.getItem("sus-id"));
                });
                remove.addEventListener('click', (event) => {
      
                sessionStorage.setItem('remo-id', remove.getAttribute('data-id'));
                console.log(sessionStorage.getItem("remo-id"));
                });
          })
    })
  })
  .catch(err => {
    console.log("An Error Occured: "+err);
  })
