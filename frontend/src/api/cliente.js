import axios from 'axios'

const cliente = axios.create({
  baseURL: 'http://localhost',
  withCredentials: true,
  withXSRFToken: true,
})

export default cliente