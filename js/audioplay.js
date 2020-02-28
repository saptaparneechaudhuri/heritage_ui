function continousPlay(id) {
    console.log("continous play called");
    console.log(id);
    document.getElementById('button-clicked').value = id + '_next_navigation';
    jQuery('#edit-' + id).trigger('change');
}