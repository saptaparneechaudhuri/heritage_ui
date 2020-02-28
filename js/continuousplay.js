function nextPlay() {

// var x = document.getElementById("audio-play"); 
// x.loop = true;
// x.load();

// var chapter = parseInt(document.getElementById('edit-chapter').value);


// // Get the total number of options
// var chapterMax = document.getElementById('edit-chapter').options.length;

// var position = 1;

// if(chapterMax > 0) {
// 	for(let i=0;i<chapterMax;i++) {
// 		position += 1;
// 	}
// }


var request = new XMLHttpRequest();
var chapterMax = document.getElementById('edit-chapter').options.length;


var urls = [];
var audios = [];


for(let i=1;i<=2;i++) {
	var url = 'http://172.27.13.38/api/25257?position=' + i + '&field_onelevel_25280_audio=1';
	urls.push(url);
 //   request.open('GET', url, true);
   
//     request.onreadystatechange=(e)=>{
// 	JSON.parse(request.responseText, (key ,value) => {
// 		if(key == 'audio') {
// 			audios.push(value)
			
// 			// var x = document.getElementById("audio-play"); 
// 			// console.log(value);
// 			// x.src = value;
// 			// x.load();
// 		}
// 	}
// 		);
	
	
	
// }


//request.send();
}

for(let i=0;i<urls.length;i++){
	var request = new XMLHttpRequest();
    request.open('GET', urls[i], true);
    request.send();
    request.onreadystatechange=(e)=>{
    	JSON.parse(request.responseText, (key,value) => {
    		console.log(request.responseText);
    		if(key == 'audio') {
    			audios.push(value)
    		}
    	});

    		
    	}

    	

    }









console.log(audios);
// request.open('GET', url, true);

// request.send();

// request.onreadystatechange=(e)=>{
// 	JSON.parse(request.responseText, (key ,value) => {
// 		if(key == 'audio') {
// 			var posit
// 			var x = document.getElementById("audio-play"); 
// 			console.log(value);
// 			x.src = value;
// 			x.load();
// 		}
// 	}
// 		);
	
	
	
// }








}






