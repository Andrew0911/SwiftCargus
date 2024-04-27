import React, { useState } from 'react'
import { Helmet } from "react-helmet";
import SwiftCargusLogo from '../img/SwiftCargusLogo.png'
import { useLocation } from 'react-router-dom';

function Tracking() {
  
  const location = useLocation();
  const searchParams = new URLSearchParams(location.search);
  const awbNumber = searchParams.get('awbNumber');
  const [awb, setAwb] = useState("");

  const handleInputChange = (event) => {
    let value = event.target.value;
    value = value.replace(/[^0-9]/g, '');
    value = value.replace(/^0+/, '');
    if (value === '' || value === '0') {
      value = '0';
    }
    event.target.value = value;
    setAwb(value);
  };

  const trackAWB = async (ev) => {
    ev.preventDefault();
    try {
      console.log(2);
         
    } catch (error) {
    console.error('Error tracking the AWB', error);
  }
  }

  return (
    <>
      <Helmet>
        <title>Tracking</title>
        <link rel="icon" href={SwiftCargusLogo} type="image/png" />
      </Helmet>

      <div className='page-header'>AWB Tracking</div>
      <br/> <br/> <br/>

      {!awbNumber &&
        <div style={{display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '1vw'}}>
          <div style={{fontFamily: 'Quicksand', fontSize: '21px', color: 'var(--yellow-color)'}}> AWB Number </div>
          <input 
            className='input-field'
            type='text'
            value={awb} 
            style={{ width: '23vw', height: '4vh', fontSize: '18px' }}
            onChange={handleInputChange} 
            maxLength="10"
          />
          {awb.length === 10 &&
            <button className='dynamic-button' onClick={(ev) => trackAWB(ev)}> 
              Track
            </button>

          }
        </div>
      }

    </>
  )
}

export default Tracking