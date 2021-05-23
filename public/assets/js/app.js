const form = document.getElementById('form')
const submitBtn = document.getElementById('submit_btn')

if (form) {
  form.addEventListener(
    'submit', () => {
      submitBtn.disabled = true
    }
  )
}

const moreLinks = document.querySelectorAll('.matches__match__more')

if (moreLinks) {
  moreLinks.forEach(
    item => {
      item.addEventListener(
        'click', () => {
        let details = item.parentNode.querySelector('.matches__match__details').style
        details.display === 'none' ?
        details.display = 'block' :
        details.display = 'none' 
        }
      )
    }
  )
}